<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Model\Outbox\Outbox;
use Acme\SellerRefund\Model\ResourceModel\Refund\CollectionFactory as RefundCollectionFactory;
use DateTimeInterface;

/**
 * Safety-net sweep for the Read Refund Status step: finds refunds sitting in cash_refunded
 * and enqueues a status check for any that has no pending outbox job. The status filter and
 * created_at bound are served by the MP_REFUND_STATUS_CREATED_AT index.
 */
class Reconciler
{
    public function __construct(
        private readonly RefundCollectionFactory $collectionFactory,
        private readonly Outbox $outbox
    ) {
    }

    public function run(DateTimeInterface $now, int $batch): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addStatusFilter(RefundInterface::STATUS_CASH_REFUNDED)
            ->addCreatedBeforeFilter($now->format('Y-m-d H:i:s'))
            ->setPageSize($batch)
            ->setCurPage(1);

        $enqueued = 0;
        foreach ($collection as $refund) {
            $refundId = (int) $refund->getEntityId();
            if (!$this->outbox->hasPending($refundId)) {
                $this->outbox->enqueue($refundId, Outbox::OP_STATUS_CHECK);
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
