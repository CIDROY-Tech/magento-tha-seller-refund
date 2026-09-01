<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration\Framework;

use Acme\SellerRefund\Model\SellerLineResolver;
use Acme\SellerRefund\Model\Submission\RefundSubmission;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Loads the seeded orders (SR-ORD-1001..1006) by increment id and builds refund submissions
 * over their seller lines.
 */
class SeedOrders
{
    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SellerLineResolver $sellerLineResolver
    ) {
    }

    public function loadByIncrementId(string $incrementId): OrderInterface
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', $incrementId)->setPageSize(1);
        $orderId = (int) $collection->getFirstItem()->getEntityId();

        if ($orderId === 0) {
            throw new \RuntimeException(sprintf('Seeded order "%s" was not found.', $incrementId));
        }

        return $this->orderRepository->get($orderId);
    }

    /**
     * @return array<int, \Magento\Sales\Api\Data\OrderItemInterface> order_item_id => item
     */
    public function sellerLines(OrderInterface $order): array
    {
        return $this->sellerLineResolver->sellerLines($order);
    }

    /**
     * A full-refund submission: every seller line at its full ordered quantity.
     */
    public function fullSubmission(OrderInterface $order, string $reasonCode = 'defect'): RefundSubmission
    {
        $qtyByItem = [];
        foreach ($this->sellerLines($order) as $orderItemId => $item) {
            $qtyByItem[(int) $orderItemId] = sprintf('%.4F', (float) $item->getQtyOrdered());
        }

        return new RefundSubmission($reasonCode, $qtyByItem);
    }

    /**
     * A partial-refund submission for a single seller line.
     */
    public function partialSubmission(
        int $orderItemId,
        string $qty,
        string $reasonCode = 'defect'
    ): RefundSubmission {
        return new RefundSubmission($reasonCode, [$orderItemId => $qty]);
    }

    /**
     * The first seller line's order_item_id for the given order.
     */
    public function firstSellerLineId(OrderInterface $order): int
    {
        foreach ($this->sellerLines($order) as $orderItemId => $item) {
            return (int) $orderItemId;
        }

        throw new \RuntimeException('The order has no seller lines.');
    }
}
