<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel\RefundItem;

use Acme\SellerRefund\Api\Data\RefundItemInterface;
use Acme\SellerRefund\Model\RefundItem as RefundItemModel;
use Acme\SellerRefund\Model\ResourceModel\RefundItem as RefundItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(RefundItemModel::class, RefundItemResource::class);
    }

    public function addRefundFilter(int $refundId): self
    {
        $this->addFieldToFilter(RefundItemInterface::REFUND_ID, $refundId);

        return $this;
    }
}
