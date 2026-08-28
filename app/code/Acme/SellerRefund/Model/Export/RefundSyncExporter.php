<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Export;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * The canonical downstream core-system refund export carried through the standard order
 * sync. Every figure comes from the calculator (RefundFigures) so this payload agrees with
 * the receipt, the storefront surfaces and the confirmation email to the last unit.
 */
class RefundSyncExporter
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundTotalCalculator $calculator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(RefundInterface $refund): array
    {
        $items = $this->refundRepository->getItems((int) $refund->getEntityId());
        $order = $this->orderRepository->get($refund->getOrderId());
        $figures = $this->calculator->fromSnapshot($refund, $items, $order);

        return [
            'refund_no' => $refund->getRefundNo(),
            'order_increment_id' => (string) $order->getIncrementId(),
            'seller_order_id' => $refund->getSellerOrderId(),
            'status' => $refund->getStatus(),
            'refund_type' => $refund->getRefundType(),
            'erp_refund_id' => $refund->getErpRefundId(),
        ] + $figures->toArray();
    }
}
