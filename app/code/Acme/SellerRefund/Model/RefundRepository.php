<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\ResourceModel\Refund as RefundResource;
use Acme\SellerRefund\Model\ResourceModel\Refund\CollectionFactory as RefundCollectionFactory;
use Acme\SellerRefund\Model\ResourceModel\RefundItem\CollectionFactory as RefundItemCollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class RefundRepository implements RefundRepositoryInterface
{
    public function __construct(
        private readonly RefundFactory $refundFactory,
        private readonly RefundResource $refundResource,
        private readonly RefundCollectionFactory $refundCollectionFactory,
        private readonly RefundItemCollectionFactory $itemCollectionFactory
    ) {
    }

    public function getById(int $entityId): RefundInterface
    {
        $refund = $this->refundFactory->create();
        $this->refundResource->load($refund, $entityId);
        if (!$refund->getEntityId()) {
            throw new NoSuchEntityException(__('No refund exists with ID "%1".', $entityId));
        }

        return $refund;
    }

    public function getByRefundNo(string $refundNo): ?RefundInterface
    {
        $refund = $this->refundFactory->create();
        $this->refundResource->load($refund, $refundNo, RefundInterface::REFUND_NO);

        return $refund->getEntityId() ? $refund : null;
    }

    public function getByOrderId(int $orderId): array
    {
        $collection = $this->refundCollectionFactory->create();
        $collection->addFieldToFilter(RefundInterface::ORDER_ID, $orderId);
        $collection->setOrder('entity_id', $collection::SORT_ORDER_ASC);

        return array_values($collection->getItems());
    }

    public function getItems(int $refundId): array
    {
        $collection = $this->itemCollectionFactory->create();
        $collection->addRefundFilter($refundId);
        $collection->setOrder('entity_id', $collection::SORT_ORDER_ASC);

        return array_values($collection->getItems());
    }

    public function save(RefundInterface $refund): RefundInterface
    {
        if ($refund->getEntityId()) {
            throw new CouldNotSaveException(
                __('The refund repository is insert-only; updates go through the optimistic-locking path.')
            );
        }

        try {
            $this->refundResource->save($refund);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the refund: %1', $e->getMessage()), $e);
        }

        return $refund;
    }
}
