<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RefundItem extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mp_refund_item', 'entity_id');
    }
}
