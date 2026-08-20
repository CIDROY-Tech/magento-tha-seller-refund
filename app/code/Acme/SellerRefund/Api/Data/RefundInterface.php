<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Api\Data;

/**
 * One CS-initiated refund against one seller order.
 */
interface RefundInterface
{
    public const ENTITY_ID = 'entity_id';
    public const REFUND_NO = 'refund_no';
    public const ORDER_ID = 'order_id';
    public const SELLER_ORDER_ID = 'seller_order_id';
    public const REFUND_TYPE = 'refund_type';
    public const REASON_CODE = 'reason_code';
    public const STATUS = 'status';
    public const SUBTOTAL_AMOUNT = 'subtotal_amount';
    public const SHIPPING_AMOUNT = 'shipping_amount';
    public const TAX_AMOUNT = 'tax_amount';
    public const GRAND_TOTAL = 'grand_total';
    public const CURRENCY_CODE = 'currency_code';
    public const ERP_REFUND_ID = 'erp_refund_id';
    public const CREATE_STATUS = 'create_status';
    public const STATUS_CHECK_STATUS = 'status_check_status';
    public const CONFIRM_STATUS = 'confirm_status';
    public const CASH_REFUND_STATUS = 'cash_refund_status';
    public const TRANSACTION_NUMBER = 'transaction_number';
    public const TRANSACTION_DATE = 'transaction_date';
    public const VERSION = 'version';
    public const CREATED_AT = 'created_at';
    public const CREATED_BY = 'created_by';
    public const UPDATED_AT = 'updated_at';
    public const UPDATED_BY = 'updated_by';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_CASH_REFUND_PENDING = 'cash_refund_pending';
    public const STATUS_CASH_REFUNDED = 'cash_refunded';
    public const STATUS_ERP_CONFIRM_PENDING = 'erp_confirm_pending';
    public const STATUS_ERP_CONFIRMED = 'erp_confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const SUB_NOT_STARTED = 'not_started';
    public const SUB_PENDING = 'pending';
    public const SUB_SUCCEEDED = 'succeeded';
    public const SUB_RETRYABLE_ERROR = 'retryable_error';
    public const SUB_BUSINESS_REJECTED = 'business_rejected';

    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';

    public function getEntityId(): ?int;

    public function setEntityId($entityId): self;

    public function getRefundNo(): string;

    public function setRefundNo(string $refundNo): self;

    public function getOrderId(): int;

    public function setOrderId(int $orderId): self;

    public function getSellerOrderId(): ?string;

    public function setSellerOrderId(?string $sellerOrderId): self;

    public function getRefundType(): string;

    public function setRefundType(string $refundType): self;

    public function getReasonCode(): string;

    public function setReasonCode(string $reasonCode): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getSubtotalAmount(): string;

    public function setSubtotalAmount(string $subtotalAmount): self;

    public function getShippingAmount(): string;

    public function setShippingAmount(string $shippingAmount): self;

    public function getTaxAmount(): string;

    public function setTaxAmount(string $taxAmount): self;

    public function getGrandTotal(): string;

    public function setGrandTotal(string $grandTotal): self;

    public function getCurrencyCode(): string;

    public function setCurrencyCode(string $currencyCode): self;

    public function getErpRefundId(): ?string;

    public function setErpRefundId(?string $erpRefundId): self;

    public function getCreateStatus(): string;

    public function setCreateStatus(string $createStatus): self;

    public function getStatusCheckStatus(): string;

    public function setStatusCheckStatus(string $statusCheckStatus): self;

    public function getConfirmStatus(): string;

    public function setConfirmStatus(string $confirmStatus): self;

    public function getCashRefundStatus(): string;

    public function setCashRefundStatus(string $cashRefundStatus): self;

    public function getTransactionNumber(): ?string;

    public function setTransactionNumber(?string $transactionNumber): self;

    public function getTransactionDate(): ?string;

    public function setTransactionDate(?string $transactionDate): self;

    public function getVersion(): int;

    public function setVersion(int $version): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getCreatedBy(): ?int;

    public function setCreatedBy(?int $createdBy): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;

    public function getUpdatedBy(): ?int;

    public function setUpdatedBy(?int $updatedBy): self;
}
