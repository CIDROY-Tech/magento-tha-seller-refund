<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Atomic per-day counter for refund numbers. A single INSERT ... ON DUPLICATE KEY UPDATE
 * both advances and returns the next value without a read-modify-write race.
 */
class Sequence
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function next(string $dateKey): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('mp_refund_sequence');

        $sql = sprintf(
            'INSERT INTO %s (%s, %s) VALUES (?, LAST_INSERT_ID(1)) '
            . 'ON DUPLICATE KEY UPDATE %s = LAST_INSERT_ID(%s + 1)',
            $connection->quoteIdentifier($table),
            $connection->quoteIdentifier('date_key'),
            $connection->quoteIdentifier('current_value'),
            $connection->quoteIdentifier('current_value'),
            $connection->quoteIdentifier('current_value')
        );

        $connection->query($sql, [$dateKey]);

        return (int) $connection->lastInsertId($table);
    }
}
