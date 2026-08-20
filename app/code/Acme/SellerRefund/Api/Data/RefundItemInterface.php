<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Api\Data;

/**
 * Immutable snapshot of one refunded line.
 */
interface RefundItemInterface
{
    public const ENTITY_ID = 'entity_id';
    public const REFUND_ID = 'refund_id';
    public const ORDER_ITEM_ID = 'order_item_id';
    public const SKU = 'sku';
    public const PRODUCT_NAME = 'product_name';
    public const QTY_ORDERED = 'qty_ordered';
    public const QTY_REFUNDED_BEFORE = 'qty_refunded_before';
    public const QTY_REFUND = 'qty_refund';
    public const QTY_REFUNDABLE_AFTER = 'qty_refundable_after';
    public const UNIT_PRICE = 'unit_price';
    public const ROW_AMOUNT = 'row_amount';
    public const SHIPPING_AMOUNT = 'shipping_amount';
    public const TAX_RATE = 'tax_rate';
    public const TAX_CODE = 'tax_code';
    public const TAX_AMOUNT = 'tax_amount';
    public const GRAND_TOTAL = 'grand_total';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getEntityId(): ?int;

    public function setEntityId($entityId): self;

    public function getRefundId(): int;

    public function setRefundId(int $refundId): self;

    public function getOrderItemId(): int;

    public function setOrderItemId(int $orderItemId): self;

    public function getSku(): string;

    public function setSku(string $sku): self;

    public function getProductName(): string;

    public function setProductName(string $productName): self;

    public function getQtyOrdered(): string;

    public function setQtyOrdered(string $qtyOrdered): self;

    public function getQtyRefundedBefore(): string;

    public function setQtyRefundedBefore(string $qtyRefundedBefore): self;

    public function getQtyRefund(): string;

    public function setQtyRefund(string $qtyRefund): self;

    public function getQtyRefundableAfter(): string;

    public function setQtyRefundableAfter(string $qtyRefundableAfter): self;

    public function getUnitPrice(): string;

    public function setUnitPrice(string $unitPrice): self;

    public function getRowAmount(): string;

    public function setRowAmount(string $rowAmount): self;

    public function getShippingAmount(): string;

    public function setShippingAmount(string $shippingAmount): self;

    public function getTaxRate(): string;

    public function setTaxRate(string $taxRate): self;

    public function getTaxCode(): string;

    public function setTaxCode(string $taxCode): self;

    public function getTaxAmount(): string;

    public function setTaxAmount(string $taxAmount): self;

    public function getGrandTotal(): string;

    public function setGrandTotal(string $grandTotal): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
