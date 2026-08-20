<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundItemInterface;
use Acme\SellerRefund\Model\ResourceModel\RefundItem as RefundItemResource;
use Magento\Framework\Model\AbstractModel;

class RefundItem extends AbstractModel implements RefundItemInterface
{
    protected function _construct(): void
    {
        $this->_init(RefundItemResource::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setEntityId($entityId): RefundItemInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getRefundId(): int
    {
        return (int) $this->getData(self::REFUND_ID);
    }

    public function setRefundId(int $refundId): RefundItemInterface
    {
        return $this->setData(self::REFUND_ID, $refundId);
    }

    public function getOrderItemId(): int
    {
        return (int) $this->getData(self::ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $orderItemId): RefundItemInterface
    {
        return $this->setData(self::ORDER_ITEM_ID, $orderItemId);
    }

    public function getSku(): string
    {
        return (string) $this->getData(self::SKU);
    }

    public function setSku(string $sku): RefundItemInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getProductName(): string
    {
        return (string) $this->getData(self::PRODUCT_NAME);
    }

    public function setProductName(string $productName): RefundItemInterface
    {
        return $this->setData(self::PRODUCT_NAME, $productName);
    }

    public function getQtyOrdered(): string
    {
        return (string) $this->getData(self::QTY_ORDERED);
    }

    public function setQtyOrdered(string $qtyOrdered): RefundItemInterface
    {
        return $this->setData(self::QTY_ORDERED, $qtyOrdered);
    }

    public function getQtyRefundedBefore(): string
    {
        return (string) $this->getData(self::QTY_REFUNDED_BEFORE);
    }

    public function setQtyRefundedBefore(string $qtyRefundedBefore): RefundItemInterface
    {
        return $this->setData(self::QTY_REFUNDED_BEFORE, $qtyRefundedBefore);
    }

    public function getQtyRefund(): string
    {
        return (string) $this->getData(self::QTY_REFUND);
    }

    public function setQtyRefund(string $qtyRefund): RefundItemInterface
    {
        return $this->setData(self::QTY_REFUND, $qtyRefund);
    }

    public function getQtyRefundableAfter(): string
    {
        return (string) $this->getData(self::QTY_REFUNDABLE_AFTER);
    }

    public function setQtyRefundableAfter(string $qtyRefundableAfter): RefundItemInterface
    {
        return $this->setData(self::QTY_REFUNDABLE_AFTER, $qtyRefundableAfter);
    }

    public function getUnitPrice(): string
    {
        return (string) $this->getData(self::UNIT_PRICE);
    }

    public function setUnitPrice(string $unitPrice): RefundItemInterface
    {
        return $this->setData(self::UNIT_PRICE, $unitPrice);
    }

    public function getRowAmount(): string
    {
        return (string) $this->getData(self::ROW_AMOUNT);
    }

    public function setRowAmount(string $rowAmount): RefundItemInterface
    {
        return $this->setData(self::ROW_AMOUNT, $rowAmount);
    }

    public function getShippingAmount(): string
    {
        return (string) $this->getData(self::SHIPPING_AMOUNT);
    }

    public function setShippingAmount(string $shippingAmount): RefundItemInterface
    {
        return $this->setData(self::SHIPPING_AMOUNT, $shippingAmount);
    }

    public function getTaxRate(): string
    {
        return (string) $this->getData(self::TAX_RATE);
    }

    public function setTaxRate(string $taxRate): RefundItemInterface
    {
        return $this->setData(self::TAX_RATE, $taxRate);
    }

    public function getTaxCode(): string
    {
        return (string) $this->getData(self::TAX_CODE);
    }

    public function setTaxCode(string $taxCode): RefundItemInterface
    {
        return $this->setData(self::TAX_CODE, $taxCode);
    }

    public function getTaxAmount(): string
    {
        return (string) $this->getData(self::TAX_AMOUNT);
    }

    public function setTaxAmount(string $taxAmount): RefundItemInterface
    {
        return $this->setData(self::TAX_AMOUNT, $taxAmount);
    }

    public function getGrandTotal(): string
    {
        return (string) $this->getData(self::GRAND_TOTAL);
    }

    public function setGrandTotal(string $grandTotal): RefundItemInterface
    {
        return $this->setData(self::GRAND_TOTAL, $grandTotal);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $createdAt): RefundItemInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $updatedAt): RefundItemInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
