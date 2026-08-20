<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Total;

/**
 * One refunded seller line as computed by the calculator. All money is a 4-decimal string.
 */
final class RefundFigureLine
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $qtyRefund,
        public readonly string $unitPrice,
        public readonly string $rowAmount,
        public readonly string $shippingAmount,
        public readonly string $taxRate,
        public readonly string $taxCode,
        public readonly string $taxAmount,
        public readonly string $grandTotal
    ) {
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'order_item_id' => $this->orderItemId,
            'sku' => $this->sku,
            'product_name' => $this->productName,
            'qty_refund' => $this->qtyRefund,
            'unit_price' => $this->unitPrice,
            'row_amount' => $this->rowAmount,
            'shipping_amount' => $this->shippingAmount,
            'tax_rate' => $this->taxRate,
            'tax_code' => $this->taxCode,
            'tax_amount' => $this->taxAmount,
            'grand_total' => $this->grandTotal,
        ];
    }
}
