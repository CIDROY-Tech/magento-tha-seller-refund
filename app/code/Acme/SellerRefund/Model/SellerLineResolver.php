<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Distinguishes seller lines (carrying a seller code) from first-party lines on an order.
 */
class SellerLineResolver
{
    public function isSellerLine(OrderItemInterface $orderItem): bool
    {
        $code = $orderItem->getData('mp_seller_code');

        return $code !== null && $code !== '';
    }

    /**
     * @return array<int, OrderItemInterface> keyed by order item id
     */
    public function sellerLines(OrderInterface $order): array
    {
        $lines = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if ($this->isSellerLine($item)) {
                $lines[(int) $item->getItemId()] = $item;
            }
        }

        return $lines;
    }
}
