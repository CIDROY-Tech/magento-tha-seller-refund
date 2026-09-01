<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration\Framework;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Opens a second, independent database connection from app/etc/env.php. Used by concurrency
 * scenarios that need a session distinct from the test's own transaction; its lock wait is
 * kept short so a blocked statement fails fast rather than hanging the suite.
 */
class SecondConnection
{
    private ?AdapterInterface $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connectionFactory,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly ResourceConnection $resource
    ) {
    }

    public function connection(): AdapterInterface
    {
        if ($this->connection === null) {
            /** @var array<string, mixed> $config */
            $config = (array) $this->deploymentConfig->get('db/connection/default');
            $this->connection = $this->connectionFactory->create($config);
            $this->connection->query('SET SESSION innodb_lock_wait_timeout = 2');
        }

        return $this->connection;
    }

    /**
     * Hard-delete every refund (and its children) for an order on this independent connection.
     * Runs outside the test transaction, so it is only for fixtures this connection created.
     */
    public function cleanupRefundsForOrder(int $orderId): void
    {
        $connection = $this->connection();
        $refundTable = $this->resource->getTableName('mp_refund');
        $itemTable = $this->resource->getTableName('mp_refund_item');
        $eventTable = $this->resource->getTableName('mp_refund_event');
        $outboxTable = $this->resource->getTableName('mp_refund_outbox');

        $refundIds = $connection->fetchCol(
            $connection->select()->from($refundTable, ['entity_id'])->where('order_id = ?', $orderId)
        );

        if ($refundIds === []) {
            return;
        }

        $connection->delete($eventTable, ['refund_id IN (?)' => $refundIds]);
        $connection->delete($itemTable, ['refund_id IN (?)' => $refundIds]);
        $connection->delete($outboxTable, ['refund_id IN (?)' => $refundIds]);
        $connection->delete($refundTable, ['entity_id IN (?)' => $refundIds]);
    }
}
