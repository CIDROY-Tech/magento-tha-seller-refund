<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Outbox\Outbox;
use Acme\SellerRefund\Service\RefundProcessor;
use Acme\SellerRefund\Test\Integration\Framework\DbTransactionTestCase;
use Acme\SellerRefund\Test\Integration\Framework\SeedOrders;
use Magento\Framework\App\ResourceConnection;

/**
 * Submitting a partial refund persists the local aggregate and enqueues the ERP create job
 * atomically, and issues no HTTP.
 */
class SubmitRefundTest extends DbTransactionTestCase
{
    public function testSubmitPersistsCalculatedRefundItemsOutboxAndEvent(): void
    {
        $seed = $this->get(SeedOrders::class);
        $order = $seed->loadByIncrementId('SR-ORD-1001');
        $orderId = (int) $order->getEntityId();

        // Refund 1 of the 2 ordered units, so this is a partial refund.
        $submission = $seed->partialSubmission($seed->firstSellerLineId($order), '1');

        $refund = $this->get(RefundProcessor::class)->submit($orderId, $submission);
        $refundId = (int) $refund->getEntityId();
        self::assertGreaterThan(0, $refundId);

        $repository = $this->get(RefundRepositoryInterface::class);
        $stored = $repository->getById($refundId);
        self::assertSame(RefundInterface::STATUS_CALCULATED, $stored->getStatus());
        self::assertSame(1, $stored->getVersion());
        self::assertSame(RefundInterface::TYPE_PARTIAL, $stored->getRefundType());

        $items = $repository->getItems($refundId);
        self::assertCount(1, $items);
        self::assertSame('SELLER-RED-01', $items[0]->getSku());
        self::assertSame('1.0000', $items[0]->getQtyRefund());

        $resource = $this->get(ResourceConnection::class);
        $connection = $this->connection();

        $outboxRow = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('mp_refund_outbox'), ['entity_id'])
                ->where('refund_id = ?', $refundId)
                ->where('operation = ?', Outbox::OP_CREATE)
                ->where('state = ?', Outbox::STATE_PENDING)
        );
        self::assertNotFalse($outboxRow, 'A pending create job should be enqueued.');

        $eventRow = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('mp_refund_event'), ['entity_id'])
                ->where('refund_id = ?', $refundId)
                ->where('event_type = ?', RefundEventInterface::TYPE_CREATED)
        );
        self::assertNotFalse($eventRow, 'A created event should be recorded.');
    }
}
