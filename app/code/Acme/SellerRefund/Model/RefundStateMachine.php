<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Exception\IllegalTransitionException;
use Acme\SellerRefund\Exception\StaleRefundException;
use Acme\SellerRefund\Model\Event\EventRecorder;
use Acme\SellerRefund\Model\ResourceModel\Refund as RefundResource;

/**
 * Guards the main lifecycle: legal transitions only, applied through an optimistic
 * compare-and-set so two concurrent workers cannot both advance the same refund.
 */
class RefundStateMachine
{
    private const SUB_STATUS_FIELDS = [
        RefundInterface::CREATE_STATUS,
        RefundInterface::STATUS_CHECK_STATUS,
        RefundInterface::CONFIRM_STATUS,
        RefundInterface::CASH_REFUND_STATUS,
    ];

    /**
     * @var array<string, string[]>
     */
    private const ALLOWED = [
        RefundInterface::STATUS_DRAFT => [RefundInterface::STATUS_CALCULATED],
        RefundInterface::STATUS_CALCULATED => [
            RefundInterface::STATUS_CASH_REFUND_PENDING,
            RefundInterface::STATUS_FAILED,
            RefundInterface::STATUS_CANCELLED,
        ],
        RefundInterface::STATUS_CASH_REFUND_PENDING => [
            RefundInterface::STATUS_CASH_REFUNDED,
            RefundInterface::STATUS_CANCELLED,
        ],
        RefundInterface::STATUS_CASH_REFUNDED => [RefundInterface::STATUS_ERP_CONFIRM_PENDING],
        RefundInterface::STATUS_ERP_CONFIRM_PENDING => [RefundInterface::STATUS_ERP_CONFIRMED],
    ];

    public function __construct(
        private readonly RefundResource $refundResource,
        private readonly EventRecorder $eventRecorder
    ) {
    }

    /**
     * @param array<string, mixed> $extra additional columns to write in the same CAS update
     *
     * @throws IllegalTransitionException
     * @throws StaleRefundException
     */
    public function transition(
        RefundInterface $refund,
        string $to,
        string $eventType = RefundEventInterface::TYPE_TRANSITION,
        array $extra = [],
        ?int $actorId = null
    ): void {
        $from = $refund->getStatus();
        if (!$this->isLegal($from, $to)) {
            throw new IllegalTransitionException(
                __('A refund cannot move from "%1" to "%2".', $from, $to)
            );
        }

        $data = $extra;
        $data[RefundInterface::STATUS] = $to;
        if ($actorId !== null) {
            $data[RefundInterface::UPDATED_BY] = $actorId;
        }

        $this->applyCas($refund, $data);

        $this->eventRecorder->record(
            (int) $refund->getEntityId(),
            RefundEventInterface::TYPE_TRANSITION,
            RefundInterface::SUB_SUCCEEDED,
            [
                'api_code' => $eventType,
                'status_from' => $from,
                'status_to' => $to,
                'created_by' => $actorId,
            ]
        );
    }

    /**
     * @throws StaleRefundException
     */
    public function setSubStatus(RefundInterface $refund, string $field, string $value): void
    {
        if (!in_array($field, self::SUB_STATUS_FIELDS, true)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a refund sub-status field.', $field));
        }

        $this->applyCas($refund, [$field => $value]);
    }

    public function canCancel(RefundInterface $refund): bool
    {
        return in_array(
            $refund->getStatus(),
            [RefundInterface::STATUS_CALCULATED, RefundInterface::STATUS_CASH_REFUND_PENDING],
            true
        );
    }

    private function isLegal(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws StaleRefundException
     */
    private function applyCas(RefundInterface $refund, array $data): void
    {
        $updated = $this->refundResource->updateWithVersion(
            (int) $refund->getEntityId(),
            $refund->getVersion(),
            $data
        );

        if (!$updated) {
            throw new StaleRefundException(
                __('The refund was modified by another process; reload it and try again.')
            );
        }

        foreach ($data as $column => $value) {
            $refund->setData($column, $value);
        }
        $refund->setVersion($refund->getVersion() + 1);
    }
}
