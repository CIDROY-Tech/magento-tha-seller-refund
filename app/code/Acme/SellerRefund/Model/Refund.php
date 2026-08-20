<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Model\ResourceModel\Refund as RefundResource;
use Magento\Framework\Model\AbstractModel;

class Refund extends AbstractModel implements RefundInterface
{
    protected function _construct(): void
    {
        $this->_init(RefundResource::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setEntityId($entityId): RefundInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getRefundNo(): string
    {
        return (string) $this->getData(self::REFUND_NO);
    }

    public function setRefundNo(string $refundNo): RefundInterface
    {
        return $this->setData(self::REFUND_NO, $refundNo);
    }

    public function getOrderId(): int
    {
        return (int) $this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): RefundInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getSellerOrderId(): ?string
    {
        $value = $this->getData(self::SELLER_ORDER_ID);

        return $value === null ? null : (string) $value;
    }

    public function setSellerOrderId(?string $sellerOrderId): RefundInterface
    {
        return $this->setData(self::SELLER_ORDER_ID, $sellerOrderId);
    }

    public function getRefundType(): string
    {
        return (string) $this->getData(self::REFUND_TYPE);
    }

    public function setRefundType(string $refundType): RefundInterface
    {
        return $this->setData(self::REFUND_TYPE, $refundType);
    }

    public function getReasonCode(): string
    {
        return (string) $this->getData(self::REASON_CODE);
    }

    public function setReasonCode(string $reasonCode): RefundInterface
    {
        return $this->setData(self::REASON_CODE, $reasonCode);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): RefundInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getSubtotalAmount(): string
    {
        return (string) $this->getData(self::SUBTOTAL_AMOUNT);
    }

    public function setSubtotalAmount(string $subtotalAmount): RefundInterface
    {
        return $this->setData(self::SUBTOTAL_AMOUNT, $subtotalAmount);
    }

    public function getShippingAmount(): string
    {
        return (string) $this->getData(self::SHIPPING_AMOUNT);
    }

    public function setShippingAmount(string $shippingAmount): RefundInterface
    {
        return $this->setData(self::SHIPPING_AMOUNT, $shippingAmount);
    }

    public function getTaxAmount(): string
    {
        return (string) $this->getData(self::TAX_AMOUNT);
    }

    public function setTaxAmount(string $taxAmount): RefundInterface
    {
        return $this->setData(self::TAX_AMOUNT, $taxAmount);
    }

    public function getGrandTotal(): string
    {
        return (string) $this->getData(self::GRAND_TOTAL);
    }

    public function setGrandTotal(string $grandTotal): RefundInterface
    {
        return $this->setData(self::GRAND_TOTAL, $grandTotal);
    }

    public function getCurrencyCode(): string
    {
        return (string) $this->getData(self::CURRENCY_CODE);
    }

    public function setCurrencyCode(string $currencyCode): RefundInterface
    {
        return $this->setData(self::CURRENCY_CODE, $currencyCode);
    }

    public function getErpRefundId(): ?string
    {
        $value = $this->getData(self::ERP_REFUND_ID);

        return $value === null ? null : (string) $value;
    }

    public function setErpRefundId(?string $erpRefundId): RefundInterface
    {
        return $this->setData(self::ERP_REFUND_ID, $erpRefundId);
    }

    public function getCreateStatus(): string
    {
        return (string) $this->getData(self::CREATE_STATUS);
    }

    public function setCreateStatus(string $createStatus): RefundInterface
    {
        return $this->setData(self::CREATE_STATUS, $createStatus);
    }

    public function getStatusCheckStatus(): string
    {
        return (string) $this->getData(self::STATUS_CHECK_STATUS);
    }

    public function setStatusCheckStatus(string $statusCheckStatus): RefundInterface
    {
        return $this->setData(self::STATUS_CHECK_STATUS, $statusCheckStatus);
    }

    public function getConfirmStatus(): string
    {
        return (string) $this->getData(self::CONFIRM_STATUS);
    }

    public function setConfirmStatus(string $confirmStatus): RefundInterface
    {
        return $this->setData(self::CONFIRM_STATUS, $confirmStatus);
    }

    public function getCashRefundStatus(): string
    {
        return (string) $this->getData(self::CASH_REFUND_STATUS);
    }

    public function setCashRefundStatus(string $cashRefundStatus): RefundInterface
    {
        return $this->setData(self::CASH_REFUND_STATUS, $cashRefundStatus);
    }

    public function getTransactionNumber(): ?string
    {
        $value = $this->getData(self::TRANSACTION_NUMBER);

        return $value === null ? null : (string) $value;
    }

    public function setTransactionNumber(?string $transactionNumber): RefundInterface
    {
        return $this->setData(self::TRANSACTION_NUMBER, $transactionNumber);
    }

    public function getTransactionDate(): ?string
    {
        $value = $this->getData(self::TRANSACTION_DATE);

        return $value === null ? null : (string) $value;
    }

    public function setTransactionDate(?string $transactionDate): RefundInterface
    {
        return $this->setData(self::TRANSACTION_DATE, $transactionDate);
    }

    public function getVersion(): int
    {
        return (int) $this->getData(self::VERSION);
    }

    public function setVersion(int $version): RefundInterface
    {
        return $this->setData(self::VERSION, $version);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $createdAt): RefundInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getCreatedBy(): ?int
    {
        $value = $this->getData(self::CREATED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setCreatedBy(?int $createdBy): RefundInterface
    {
        return $this->setData(self::CREATED_BY, $createdBy);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $updatedAt): RefundInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getUpdatedBy(): ?int
    {
        $value = $this->getData(self::UPDATED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setUpdatedBy(?int $updatedBy): RefundInterface
    {
        return $this->setData(self::UPDATED_BY, $updatedBy);
    }
}
