<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Service\RefundProcessor;
use Acme\SellerRefund\Test\Integration\Framework\DbTransactionTestCase;
use Acme\SellerRefund\Test\Integration\Framework\ErpStub;
use Acme\SellerRefund\Test\Integration\Framework\SeedOrders;

/**
 * The Create Refund happy path drives one ERP create call and records exactly one refund at
 * the stub.
 */
class CreateRefundHappyPathTest extends DbTransactionTestCase
{
    public function testProcessCreatesRefundExactlyOnce(): void
    {
        $stub = $this->get(ErpStub::class);
        $stub->reset();
        $stub->setFailmode('ok');

        $seed = $this->get(SeedOrders::class);
        $order = $seed->loadByIncrementId('SR-ORD-1001');
        $orderId = (int) $order->getEntityId();
        $submission = $seed->partialSubmission($seed->firstSellerLineId($order), '1');

        $processor = $this->get(RefundProcessor::class);
        $refund = $processor->submit($orderId, $submission);
        $refundId = (int) $refund->getEntityId();

        $processor->process($refundId);

        $stored = $this->get(RefundRepositoryInterface::class)->getById($refundId);
        self::assertSame(RefundInterface::STATUS_CASH_REFUND_PENDING, $stored->getStatus());
        self::assertSame(RefundInterface::SUB_SUCCEEDED, $stored->getCreateStatus());
        self::assertNotEmpty($stored->getErpRefundId());

        self::assertSame(1, $stub->uniqueRefunds());
    }
}
