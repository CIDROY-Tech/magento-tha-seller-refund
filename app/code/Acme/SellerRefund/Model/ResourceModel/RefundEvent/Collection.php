<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel\RefundEvent;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Acme\SellerRefund\Model\RefundEvent as RefundEventModel;
use Acme\SellerRefund\Model\ResourceModel\RefundEvent as RefundEventResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(RefundEventModel::class, RefundEventResource::class);
    }

    public function addRefundFilter(int $refundId): self
    {
        $this->addFieldToFilter(RefundEventInterface::REFUND_ID, $refundId);
        $this->setOrder('entity_id', self::SORT_ORDER_ASC);

        return $this;
    }
}
