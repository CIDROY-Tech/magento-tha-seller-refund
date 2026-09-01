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
 * A definitive ERP business rejection at Create moves the refund to failed with a
 * business_rejected create sub-status.
 */
class BusinessRejectTest extends DbTransactionTestCase
{
    public function testBusinessRejectFailsTheRefund(): void
    {
        $stub = $this->get(ErpStub::class);
        $stub->reset();
        $stub->setFailmode('business_reject');

        $seed = $this->get(SeedOrders::class);
        $order = $seed->loadByIncrementId('SR-ORD-1001');
        $orderId = (int) $order->getEntityId();
        $submission = $seed->partialSubmission($seed->firstSellerLineId($order), '1');

        $processor = $this->get(RefundProcessor::class);
        $refund = $processor->submit($orderId, $submission);
        $refundId = (int) $refund->getEntityId();

        $processor->process($refundId);

        $stored = $this->get(RefundRepositoryInterface::class)->getById($refundId);
        self::assertSame(RefundInterface::STATUS_FAILED, $stored->getStatus());
        self::assertSame(RefundInterface::SUB_BUSINESS_REJECTED, $stored->getCreateStatus());
    }
}
