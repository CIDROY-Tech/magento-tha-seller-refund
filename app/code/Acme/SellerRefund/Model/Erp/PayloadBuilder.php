<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\Data\RefundItemInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds the ERP Refund API request bodies from the stored refund snapshot (FRD 9.1).
 */
class PayloadBuilder
{
    private const TAX_MODE = 'TAX_EXCLUDED';

    /**
     * @param RefundItemInterface[] $items
     *
     * @return array<string, mixed>
     */
    public function build(RefundInterface $refund, array $items, OrderInterface $order): array
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'sku' => $item->getSku(),
                'quantity' => (int) $item->getQtyRefund(),
                'amount' => $item->getRowAmount(),
                'taxes' => $this->buildTaxes($item),
            ];
        }

        return [
            'refund_no' => $refund->getRefundNo(),
            'seller_order_id' => $refund->getSellerOrderId(),
            'tax_mode' => self::TAX_MODE,
            'currency' => $refund->getCurrencyCode(),
            'lines' => $lines,
            'shipping_amount' => $refund->getShippingAmount(),
            'grand_total' => $refund->getGrandTotal(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function buildTaxes(RefundItemInterface $item): array
    {
        return [
            [
                'code' => $item->getTaxCode(),
                'amount' => $item->getTaxAmount(),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function buildConfirm(RefundInterface $refund): array
    {
        return [
            'transaction_number' => $refund->getTransactionNumber(),
            'transaction_date' => $refund->getTransactionDate(),
        ];
    }
}
