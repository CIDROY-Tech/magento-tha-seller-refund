<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Api\Data;

/**
 * Append-only audit event for a refund.
 */
interface RefundEventInterface
{
    public const ENTITY_ID = 'entity_id';
    public const REFUND_ID = 'refund_id';
    public const EVENT_TYPE = 'event_type';
    public const API_CODE = 'api_code';
    public const EVENT_STATUS = 'event_status';
    public const STATUS_FROM = 'status_from';
    public const STATUS_TO = 'status_to';
    public const REQUEST_PAYLOAD = 'request_payload';
    public const RESPONSE_PAYLOAD = 'response_payload';
    public const ERROR_CODE = 'error_code';
    public const ATTEMPT_NO = 'attempt_no';
    public const CREATED_AT = 'created_at';
    public const CREATED_BY = 'created_by';

    public const TYPE_CREATED = 'created';
    public const TYPE_TRANSITION = 'transition';
    public const TYPE_ERP_CREATE = 'erp_create';
    public const TYPE_ERP_STATUS = 'erp_status';
    public const TYPE_ERP_CONFIRM = 'erp_confirm';
    public const TYPE_CASH_REGISTERED = 'cash_registered';
    public const TYPE_REPLAY = 'replay';
    public const TYPE_CANCELLED = 'cancelled';

    public function getEntityId(): ?int;

    public function setEntityId($entityId): self;

    public function getRefundId(): int;

    public function setRefundId(int $refundId): self;

    public function getEventType(): string;

    public function setEventType(string $eventType): self;

    public function getApiCode(): ?string;

    public function setApiCode(?string $apiCode): self;

    public function getEventStatus(): string;

    public function setEventStatus(string $eventStatus): self;

    public function getStatusFrom(): ?string;

    public function setStatusFrom(?string $statusFrom): self;

    public function getStatusTo(): ?string;

    public function setStatusTo(?string $statusTo): self;

    public function getRequestPayload(): ?string;

    public function setRequestPayload(?string $requestPayload): self;

    public function getResponsePayload(): ?string;

    public function setResponsePayload(?string $responsePayload): self;

    public function getErrorCode(): ?string;

    public function setErrorCode(?string $errorCode): self;

    public function getAttemptNo(): int;

    public function setAttemptNo(int $attemptNo): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getCreatedBy(): ?int;

    public function setCreatedBy(?int $createdBy): self;
}
