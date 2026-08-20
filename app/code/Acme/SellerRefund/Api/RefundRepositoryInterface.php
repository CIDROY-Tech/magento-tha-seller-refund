<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Api;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\Data\RefundItemInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface RefundRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): RefundInterface;

    public function getByRefundNo(string $refundNo): ?RefundInterface;

    /**
     * @return RefundInterface[]
     */
    public function getByOrderId(int $orderId): array;

    /**
     * @return RefundItemInterface[]
     */
    public function getItems(int $refundId): array;

    /**
     * Insert-only persistence for a refund aggregate.
     *
     * @throws CouldNotSaveException
     */
    public function save(RefundInterface $refund): RefundInterface;
}
