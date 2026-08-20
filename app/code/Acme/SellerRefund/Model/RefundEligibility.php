<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Model\ResourceModel\PriorRefundQuantity;
use DateTimeImmutable;
use DateTimeInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Evaluates whether a refund may be started against an order (BR-01, BR-02, and a positive
 * remaining refundable quantity). The operator role gate is enforced at the controller.
 */
class RefundEligibility
{
    public const REASON_NO_SELLER_LINE = 'no_seller_line';
    public const REASON_NO_DELIVERY_DATE = 'no_delivery_date';
    public const REASON_WINDOW_EXPIRED = 'window_expired';
    public const REASON_NOTHING_REFUNDABLE = 'nothing_refundable';

    public function __construct(
        private readonly Config $config,
        private readonly SellerLineResolver $sellerLineResolver,
        private readonly PriorRefundQuantity $priorRefundQuantity
    ) {
    }

    public function check(OrderInterface $order, DateTimeInterface $now): EligibilityResult
    {
        $reasons = [];

        $sellerLines = $this->sellerLineResolver->sellerLines($order);
        if ($sellerLines === []) {
            $reasons[] = self::REASON_NO_SELLER_LINE;
        }

        $deliveredAt = $order->getData('mp_delivered_at');
        if ($deliveredAt === null || $deliveredAt === '') {
            $reasons[] = self::REASON_NO_DELIVERY_DATE;
        } elseif (!$this->withinWindow((string) $deliveredAt, $now, (int) $order->getStoreId())) {
            $reasons[] = self::REASON_WINDOW_EXPIRED;
        }

        if ($sellerLines !== [] && !$this->hasRemainingRefundable($order, $sellerLines)) {
            $reasons[] = self::REASON_NOTHING_REFUNDABLE;
        }

        return new EligibilityResult($reasons === [], $reasons);
    }

    private function withinWindow(string $deliveredAt, DateTimeInterface $now, int $storeId): bool
    {
        $delivered = new DateTimeImmutable($deliveredAt, $now->getTimezone());
        $deadline = $delivered->modify(sprintf('+%d days', $this->config->windowDays($storeId)));

        return $now->getTimestamp() <= $deadline->getTimestamp();
    }

    /**
     * @param array<int, \Magento\Sales\Api\Data\OrderItemInterface> $sellerLines
     */
    private function hasRemainingRefundable(OrderInterface $order, array $sellerLines): bool
    {
        $prior = $this->priorRefundQuantity->sumRefundedByOrderItem((int) $order->getEntityId());

        foreach ($sellerLines as $itemId => $item) {
            $ordered = (string) $item->getQtyOrdered();
            $refunded = (string) ($prior[$itemId] ?? '0');
            if (bccomp($ordered, $refunded, 4) === 1) {
                return true;
            }
        }

        return false;
    }
}
