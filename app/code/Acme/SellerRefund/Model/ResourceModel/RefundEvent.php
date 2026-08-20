<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RefundEvent extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mp_refund_event', 'entity_id');
    }

    /**
     * The audit log is append-only: an already-persisted event may never be re-saved.
     */
    protected function _beforeSave(AbstractModel $object): AbstractDb
    {
        if (!$object->isObjectNew()) {
            throw new \RuntimeException('Refund audit events are append-only and cannot be modified.');
        }

        return parent::_beforeSave($object);
    }
}
