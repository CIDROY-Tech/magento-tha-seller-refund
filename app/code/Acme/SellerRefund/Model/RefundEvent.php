<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Acme\SellerRefund\Model\ResourceModel\RefundEvent as RefundEventResource;
use Magento\Framework\Model\AbstractModel;

class RefundEvent extends AbstractModel implements RefundEventInterface
{
    protected function _construct(): void
    {
        $this->_init(RefundEventResource::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setEntityId($entityId): RefundEventInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getRefundId(): int
    {
        return (int) $this->getData(self::REFUND_ID);
    }

    public function setRefundId(int $refundId): RefundEventInterface
    {
        return $this->setData(self::REFUND_ID, $refundId);
    }

    public function getEventType(): string
    {
        return (string) $this->getData(self::EVENT_TYPE);
    }

    public function setEventType(string $eventType): RefundEventInterface
    {
        return $this->setData(self::EVENT_TYPE, $eventType);
    }

    public function getApiCode(): ?string
    {
        $value = $this->getData(self::API_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setApiCode(?string $apiCode): RefundEventInterface
    {
        return $this->setData(self::API_CODE, $apiCode);
    }

    public function getEventStatus(): string
    {
        return (string) $this->getData(self::EVENT_STATUS);
    }

    public function setEventStatus(string $eventStatus): RefundEventInterface
    {
        return $this->setData(self::EVENT_STATUS, $eventStatus);
    }

    public function getStatusFrom(): ?string
    {
        $value = $this->getData(self::STATUS_FROM);

        return $value === null ? null : (string) $value;
    }

    public function setStatusFrom(?string $statusFrom): RefundEventInterface
    {
        return $this->setData(self::STATUS_FROM, $statusFrom);
    }

    public function getStatusTo(): ?string
    {
        $value = $this->getData(self::STATUS_TO);

        return $value === null ? null : (string) $value;
    }

    public function setStatusTo(?string $statusTo): RefundEventInterface
    {
        return $this->setData(self::STATUS_TO, $statusTo);
    }

    public function getRequestPayload(): ?string
    {
        $value = $this->getData(self::REQUEST_PAYLOAD);

        return $value === null ? null : (string) $value;
    }

    public function setRequestPayload(?string $requestPayload): RefundEventInterface
    {
        return $this->setData(self::REQUEST_PAYLOAD, $requestPayload);
    }

    public function getResponsePayload(): ?string
    {
        $value = $this->getData(self::RESPONSE_PAYLOAD);

        return $value === null ? null : (string) $value;
    }

    public function setResponsePayload(?string $responsePayload): RefundEventInterface
    {
        return $this->setData(self::RESPONSE_PAYLOAD, $responsePayload);
    }

    public function getErrorCode(): ?string
    {
        $value = $this->getData(self::ERROR_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setErrorCode(?string $errorCode): RefundEventInterface
    {
        return $this->setData(self::ERROR_CODE, $errorCode);
    }

    public function getAttemptNo(): int
    {
        return (int) $this->getData(self::ATTEMPT_NO);
    }

    public function setAttemptNo(int $attemptNo): RefundEventInterface
    {
        return $this->setData(self::ATTEMPT_NO, $attemptNo);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $createdAt): RefundEventInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getCreatedBy(): ?int
    {
        $value = $this->getData(self::CREATED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setCreatedBy(?int $createdBy): RefundEventInterface
    {
        return $this->setData(self::CREATED_BY, $createdBy);
    }
}
