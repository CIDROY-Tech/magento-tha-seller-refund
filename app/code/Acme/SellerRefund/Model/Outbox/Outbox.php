<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Outbox;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Durable work queue backed by a DB table. enqueue() runs on the shared default connection
 * so it joins the caller's open transaction: a refund and its first outbox job commit
 * atomically. Claiming leases rows with FOR UPDATE ... SKIP LOCKED so concurrent workers
 * never process the same job.
 */
class Outbox
{
    public const OP_CREATE = 'create';
    public const OP_STATUS_CHECK = 'status_check';
    public const OP_CONFIRM = 'confirm';

    public const STATE_PENDING = 'pending';
    public const STATE_DONE = 'done';
    public const STATE_DEAD = 'dead';
    public const STATE_CANCELLED = 'cancelled';

    private const LEASE_SECONDS = 300;
    private const TABLE = 'mp_refund_outbox';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function enqueue(int $refundId, string $op, array $ctx = []): void
    {
        $connection = $this->connection();
        $connection->insert($this->table(), [
            'refund_id' => $refundId,
            'operation' => $op,
            'state' => self::STATE_PENDING,
            'attempts' => 0,
            'available_at' => new \Zend_Db_Expr('NOW()'),
            'last_error' => isset($ctx['last_error']) ? (string) $ctx['last_error'] : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claim(string $op, int $limit): array
    {
        $connection = $this->connection();
        $table = $this->table();

        $connection->beginTransaction();
        try {
            $select = $connection->select()
                ->from($table, ['entity_id'])
                ->where('state = ?', self::STATE_PENDING)
                ->where('operation = ?', $op)
                ->where('available_at <= NOW()')
                ->order('available_at ' . \Magento\Framework\DB\Select::SQL_ASC)
                ->limit($limit)
                ->forUpdate(true);

            $ids = $connection->fetchCol($select->assemble() . ' SKIP LOCKED');

            if ($ids !== []) {
                $connection->update(
                    $table,
                    ['available_at' => new \Zend_Db_Expr(sprintf('NOW() + INTERVAL %d SECOND', self::LEASE_SECONDS))],
                    ['entity_id IN (?)' => $ids]
                );
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        if ($ids === []) {
            return [];
        }

        $rows = $connection->fetchAll(
            $connection->select()->from($table)->where('entity_id IN (?)', $ids)
        );

        return $rows;
    }

    public function succeed(int $id): void
    {
        $this->connection()->update(
            $this->table(),
            ['state' => self::STATE_DONE],
            ['entity_id = ?' => $id]
        );
    }

    public function reschedule(int $id, int $delaySeconds): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'state' => self::STATE_PENDING,
                'available_at' => new \Zend_Db_Expr(sprintf('NOW() + INTERVAL %d SECOND', max(0, $delaySeconds))),
            ],
            ['entity_id = ?' => $id]
        );
    }

    public function dead(int $id): void
    {
        $this->connection()->update(
            $this->table(),
            ['state' => self::STATE_DEAD],
            ['entity_id = ?' => $id]
        );
    }

    public function fail(int $id, string $err): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'last_error' => $err,
            ],
            ['entity_id = ?' => $id]
        );
    }

    public function cancelPendingFor(int $refundId): void
    {
        $this->connection()->update(
            $this->table(),
            ['state' => self::STATE_CANCELLED],
            [
                'refund_id = ?' => $refundId,
                'state = ?' => self::STATE_PENDING,
            ]
        );
    }

    public function hasPending(int $refundId): bool
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(), ['entity_id'])
            ->where('refund_id = ?', $refundId)
            ->where('state = ?', self::STATE_PENDING)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
