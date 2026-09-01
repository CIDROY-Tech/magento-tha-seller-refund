<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Exception\IllegalTransitionException;
use Acme\SellerRefund\Exception\StaleRefundException;
use Acme\SellerRefund\Model\Event\EventRecorder;
use Acme\SellerRefund\Model\Refund;
use Acme\SellerRefund\Model\RefundStateMachine;
use Acme\SellerRefund\Model\ResourceModel\Refund as RefundResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the lifecycle guard. The resource compare-and-set is mocked so the
 * legal/illegal/stale branches can be driven without a database.
 */
class RefundStateMachineTest extends TestCase
{
    private RefundResource&MockObject $refundResource;

    private EventRecorder&MockObject $eventRecorder;

    private RefundStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->refundResource = $this->createMock(RefundResource::class);
        $this->eventRecorder = $this->createMock(EventRecorder::class);
        $this->stateMachine = new RefundStateMachine($this->refundResource, $this->eventRecorder);
    }

    public function testLegalTransitionAppliesCasAndRecordsEvent(): void
    {
        $refund = $this->refund(RefundInterface::STATUS_CALCULATED, 1);

        $this->refundResource->expects(self::once())
            ->method('updateWithVersion')
            ->with(10, 1, self::callback(static function (array $data): bool {
                return ($data[RefundInterface::STATUS] ?? null) === RefundInterface::STATUS_CASH_REFUND_PENDING;
            }))
            ->willReturn(true);

        $this->eventRecorder->expects(self::once())->method('record');

        $this->stateMachine->transition($refund, RefundInterface::STATUS_CASH_REFUND_PENDING);
    }

    public function testStaleRefundRaisesWhenNoRowUpdated(): void
    {
        $refund = $this->refund(RefundInterface::STATUS_CALCULATED, 1);

        $this->refundResource->method('updateWithVersion')->willReturn(false);
        $this->eventRecorder->expects(self::never())->method('record');

        $this->expectException(StaleRefundException::class);
        $this->stateMachine->transition($refund, RefundInterface::STATUS_CASH_REFUND_PENDING);
    }

    public function testIllegalTransitionThrowsBeforeTouchingTheResource(): void
    {
        $refund = $this->refund(RefundInterface::STATUS_CALCULATED, 1);

        $this->refundResource->expects(self::never())->method('updateWithVersion');

        $this->expectException(IllegalTransitionException::class);
        $this->stateMachine->transition($refund, RefundInterface::STATUS_ERP_CONFIRMED);
    }

    private function refund(string $status, int $version): Refund&MockObject
    {
        $refund = $this->createMock(Refund::class);
        $refund->method('getStatus')->willReturn($status);
        $refund->method('getEntityId')->willReturn(10);
        $refund->method('getVersion')->willReturn($version);

        return $refund;
    }
}
