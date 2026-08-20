<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Serialises refund creation per order by taking a row lock on the sales_order row. The
 * lock is only meaningful inside a transaction, so the caller must have opened one first.
 */
class OrderLock
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function lock(int $orderId): void
    {
        $connection = $this->resource->getConnection();

        if ($connection->getTransactionLevel() < 1) {
            throw new \RuntimeException('An order lock must be acquired inside an open transaction.');
        }

        $select = $connection->select()
            ->from($this->resource->getTableName('sales_order'), ['entity_id'])
            ->where('entity_id = ?', $orderId)
            ->forUpdate(true);

        $connection->fetchOne($select);
    }
}
