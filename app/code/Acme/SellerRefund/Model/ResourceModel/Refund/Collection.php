<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel\Refund;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Model\Refund as RefundModel;
use Acme\SellerRefund\Model\ResourceModel\Refund as RefundResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(RefundModel::class, RefundResource::class);
    }

    /**
     * Join the order-side summary columns the worklist grid renders per row, so the grid
     * does not load an order model for each refund.
     */
    public function joinOrderSummary(): self
    {
        if ($this->getFlag('order_summary_joined')) {
            return $this;
        }

        $orderTable = $this->getTable('sales_order');
        $this->getSelect()->joinLeft(
            ['so' => $orderTable],
            'main_table.order_id = so.entity_id',
            [
                'order_increment_id' => 'so.increment_id',
                'order_grand_total' => 'so.grand_total',
                'order_delivered_at' => 'so.mp_delivered_at',
                'seller_code' => 'so.mp_seller_order_id',
            ]
        );
        $this->setFlag('order_summary_joined', true);

        return $this;
    }

    /**
     * Attach per-refund line counts and refunded quantity via a grouped subselect, so the
     * grid never iterates line rows in PHP.
     */
    public function addLineSummary(): self
    {
        if ($this->getFlag('line_summary_joined')) {
            return $this;
        }

        $itemTable = $this->getTable('mp_refund_item');
        $summary = $this->getConnection()->select()
            ->from(
                $itemTable,
                [
                    'refund_id',
                    'line_count' => new \Zend_Db_Expr('COUNT(*)'),
                    'qty_refund_total' => new \Zend_Db_Expr('SUM(qty_refund)'),
                ]
            )
            ->group('refund_id');

        $this->getSelect()->joinLeft(
            ['line_summary' => $summary],
            'main_table.entity_id = line_summary.refund_id',
            ['line_count', 'qty_refund_total']
        );
        $this->setFlag('line_summary_joined', true);

        return $this;
    }

    public function addStatusFilter(string $status): self
    {
        $this->addFieldToFilter(RefundInterface::STATUS, $status);

        return $this;
    }

    public function addCreatedBeforeFilter(string $createdBefore): self
    {
        $this->addFieldToFilter(RefundInterface::CREATED_AT, ['lt' => $createdBefore]);

        return $this;
    }
}
