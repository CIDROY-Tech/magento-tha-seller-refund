<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Sums the quantity already refunded per order item, so a fresh submission can be checked
 * against the true remaining refundable quantity.
 */
class PriorRefundQuantity
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array<int|string, string> order_item_id => summed qty_refund
     */
    public function sumRefundedByOrderItem(int $orderId): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from(
                ['ri' => $this->resource->getTableName('mp_refund_item')],
                ['ri.order_item_id', 'qty' => new \Zend_Db_Expr('SUM(ri.qty_refund)')]
            )
            ->join(
                ['r' => $this->resource->getTableName('mp_refund')],
                'r.entity_id = ri.refund_id',
                []
            )
            ->where('r.order_id = ?', $orderId)
            ->where('r.status NOT IN (?)', [RefundInterface::STATUS_CANCELLED, RefundInterface::STATUS_FAILED])
            ->group('ri.order_item_id');

        return $connection->fetchPairs($select);
    }
}
