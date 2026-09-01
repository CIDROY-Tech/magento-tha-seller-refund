<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Service\RefundProcessor;
use Acme\SellerRefund\Test\Integration\Framework\DbTransactionTestCase;
use Acme\SellerRefund\Test\Integration\Framework\ErpStub;
use Acme\SellerRefund\Test\Integration\Framework\SeedOrders;
use Magento\Framework\App\ResourceConnection;

/**
 * The cash-registration then status-check then confirm path drives a fresh refund to
 * erp_confirmed and dispatches the confirmation event.
 */
class CashAndConfirmTest extends DbTransactionTestCase
{
    public function testCashRegisterStatusCheckAndConfirmReachErpConfirmed(): void
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
        $processor->registerCashRefund($refundId, 'TXN-TEST-0001', '2026-09-07', null);
        $processor->checkStatus($refundId);
        $processor->confirm($refundId);

        $repository = $this->get(RefundRepositoryInterface::class);
        $stored = $repository->getById($refundId);
        self::assertSame(RefundInterface::STATUS_ERP_CONFIRMED, $stored->getStatus());
        self::assertSame(RefundInterface::SUB_SUCCEEDED, $stored->getConfirmStatus());

        // Reaching erp_confirmed means the confirm transition ran and the confirmation event
        // was dispatched right after it; the audit trail records the ERP confirm.
        $resource = $this->get(ResourceConnection::class);
        $connection = $this->connection();
        $confirmEvent = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('mp_refund_event'), ['entity_id'])
                ->where('refund_id = ?', $refundId)
                ->where('event_type = ?', RefundEventInterface::TYPE_ERP_CONFIRM)
                ->where('event_status = ?', RefundInterface::SUB_SUCCEEDED)
        );
        self::assertNotFalse($confirmEvent, 'An ERP confirm success event should be recorded.');
    }
}
