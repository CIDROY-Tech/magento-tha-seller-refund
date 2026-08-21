<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Acme\SellerRefund\Api\Data\RefundEventInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\OrderFactory;

/**
 * Seeds the two pre-existing refunds directly into the Acme_SellerRefund tables:
 *  - SR-ORD-1003: a completed (erp_confirmed) prior refund so the remaining refundable
 *    quantity on one line is reduced, exercising the prior-refund path.
 *  - SR-ORD-1006: a refund pre-advanced to cash_refunded so the confirmation path is
 *    reachable without re-driving the whole flow.
 *
 * The rows are written with the resource connection because the consuming module's repository
 * is insert-only for new records and its resource model rejects status changes on load.
 */
class RefundSeeder
{
    private const SCALE = 4;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly OrderFactory $orderFactory
    ) {
    }

    /**
     * @return array<int, string> refund numbers created this run
     */
    public function seed(): array
    {
        $createdBy = $this->refundAdminUserId();
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd');

        $created = [];
        foreach (SeedData::orders() as $definition) {
            if ($definition['refund'] === null) {
                continue;
            }

            $order = $this->orderFactory->create();
            $order->loadByIncrementId((string) $definition['increment_id']);
            $orderId = (int) $order->getId();
            if ($orderId === 0) {
                continue;
            }
            if ($this->refundExistsForOrder($orderId)) {
                continue;
            }

            $refundNo = $this->seedRefund(
                $definition,
                $order,
                $orderId,
                $today,
                $createdBy
            );
            $created[] = $refundNo;
        }

        return $created;
    }

    public function reset(): void
    {
        $connection = $this->resource->getConnection();
        $refundTable = $this->resource->getTableName('mp_refund');
        $orderTable = $this->resource->getTableName('sales_order');

        $select = $connection->select()
            ->from($orderTable, ['entity_id'])
            ->where('increment_id IN (?)', SeedData::orderIncrementIds());
        $orderIds = $connection->fetchCol($select);
        if ($orderIds === []) {
            return;
        }

        // Deleting the parent refund cascades to items, events and outbox rows.
        $connection->delete($refundTable, ['order_id IN (?)' => $orderIds]);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function seedRefund(
        array $definition,
        \Magento\Sales\Model\Order $order,
        int $orderId,
        string $today,
        ?int $createdBy
    ): string {
        $connection = $this->resource->getConnection();
        $refund = $definition['refund'];
        $refundNo = sprintf('SR-%s-%06d', $today, (int) $refund['seq']);

        $delivered = (string) $order->getData('mp_delivered_at');
        $refundCreatedAt = (new \DateTimeImmutable($delivered, new \DateTimeZone('UTC')))
            ->modify('+1 day');
        $createdAt = $refundCreatedAt->format('Y-m-d H:i:s');
        $transactionDate = $refundCreatedAt->format('Y-m-d');

        $lines = $this->buildLines($refund['items'], $order);

        $subtotal = '0.0000';
        $taxAmount = '0.0000';
        $grandTotal = '0.0000';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['row_amount'], self::SCALE);
            $taxAmount = bcadd($taxAmount, $line['tax_amount'], self::SCALE);
            $grandTotal = bcadd($grandTotal, $line['grand_total'], self::SCALE);
        }

        $refundTable = $this->resource->getTableName('mp_refund');
        $connection->insert($refundTable, [
            'refund_no' => $refundNo,
            'order_id' => $orderId,
            'seller_order_id' => (string) $order->getData('mp_seller_order_id'),
            'refund_type' => (string) $refund['type'],
            'reason_code' => (string) $refund['reason_code'],
            'status' => (string) $refund['status'],
            'subtotal_amount' => $subtotal,
            'shipping_amount' => '0.0000',
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
            'currency_code' => 'JPY',
            'erp_refund_id' => (string) $refund['erp_refund_id'],
            'create_status' => (string) $refund['create_status'],
            'status_check_status' => (string) $refund['status_check_status'],
            'confirm_status' => (string) $refund['confirm_status'],
            'cash_refund_status' => (string) $refund['cash_refund_status'],
            'transaction_number' => (string) $refund['transaction_number'],
            'transaction_date' => $transactionDate,
            'version' => (int) $refund['version'],
            'created_at' => $createdAt,
            'created_by' => $createdBy,
            'updated_at' => $createdAt,
            'updated_by' => $createdBy,
        ]);
        $refundId = (int) $connection->lastInsertId($refundTable);

        $itemTable = $this->resource->getTableName('mp_refund_item');
        foreach ($lines as $line) {
            $connection->insert($itemTable, [
                'refund_id' => $refundId,
                'order_item_id' => $line['order_item_id'],
                'sku' => $line['sku'],
                'product_name' => $line['product_name'],
                'qty_ordered' => $line['qty_ordered'],
                'qty_refunded_before' => $line['qty_refunded_before'],
                'qty_refund' => $line['qty_refund'],
                'qty_refundable_after' => $line['qty_refundable_after'],
                'unit_price' => $line['unit_price'],
                'row_amount' => $line['row_amount'],
                'shipping_amount' => '0.0000',
                'tax_rate' => $line['tax_rate'],
                'tax_code' => $line['tax_code'],
                'tax_amount' => $line['tax_amount'],
                'grand_total' => $line['grand_total'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->seedEvents($refundId, (string) $refund['status'], $createdAt, $createdBy);

        return $refundNo;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, string|int>>
     */
    private function buildLines(array $items, \Magento\Sales\Model\Order $order): array
    {
        $orderItems = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItems[(string) $orderItem->getSku()] = $orderItem;
        }

        $lines = [];
        foreach ($items as $item) {
            $sku = (string) $item['sku'];
            if (!isset($orderItems[$sku])) {
                continue;
            }
            $orderItem = $orderItems[$sku];
            $unitPrice = $this->num($orderItem->getPrice());
            $qtyOrdered = $this->num($orderItem->getQtyOrdered());
            $qtyBefore = $this->num($item['qty_refunded_before']);
            $qtyRefund = $this->num($item['qty_refund']);
            $qtyAfter = bcsub(bcsub($qtyOrdered, $qtyBefore, self::SCALE), $qtyRefund, self::SCALE);

            $code = $this->taxCodeForSku($sku);
            $rate = $code !== null ? SeedData::TAX_RATES[$code] : '0.0000';
            $rowAmount = bcmul($unitPrice, $qtyRefund, self::SCALE);
            $taxAmount = bcmul($rowAmount, $rate, self::SCALE);
            $grandTotal = bcadd($rowAmount, $taxAmount, self::SCALE);

            $lines[] = [
                'order_item_id' => (int) $orderItem->getItemId(),
                'sku' => $sku,
                'product_name' => (string) $orderItem->getName(),
                'qty_ordered' => $qtyOrdered,
                'qty_refunded_before' => $qtyBefore,
                'qty_refund' => $qtyRefund,
                'qty_refundable_after' => $qtyAfter,
                'unit_price' => $unitPrice,
                'row_amount' => $rowAmount,
                'tax_rate' => $rate,
                'tax_code' => (string) $code,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
            ];
        }

        return $lines;
    }

    private function seedEvents(int $refundId, string $status, string $createdAt, ?int $createdBy): void
    {
        $connection = $this->resource->getConnection();
        $eventTable = $this->resource->getTableName('mp_refund_event');

        $events = [
            [RefundEventInterface::TYPE_CREATED, null, null, 'calculated'],
        ];

        if (in_array($status, ['cash_refunded', 'erp_confirmed'], true)) {
            $events[] = [RefundEventInterface::TYPE_ERP_CREATE, 'create', 'calculated', 'cash_refund_pending'];
            $events[] = [RefundEventInterface::TYPE_CASH_REGISTERED, null, 'cash_refund_pending', 'cash_refunded'];
        }
        if ($status === 'erp_confirmed') {
            $events[] = [RefundEventInterface::TYPE_ERP_STATUS, 'status_check', 'cash_refunded', 'erp_confirm_pending'];
            $events[] = [RefundEventInterface::TYPE_ERP_CONFIRM, 'confirm', 'erp_confirm_pending', 'erp_confirmed'];
        }

        foreach ($events as $event) {
            [$type, $apiCode, $from, $to] = $event;
            $connection->insert($eventTable, [
                'refund_id' => $refundId,
                'event_type' => $type,
                'api_code' => $apiCode,
                'event_status' => 'succeeded',
                'status_from' => $from,
                'status_to' => $to,
                'request_payload' => null,
                'response_payload' => null,
                'error_code' => null,
                'attempt_no' => 1,
                'created_at' => $createdAt,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function refundExistsForOrder(int $orderId): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('mp_refund'), ['entity_id'])
            ->where('order_id = ?', $orderId)
            ->limit(1);

        return $connection->fetchOne($select) !== false;
    }

    private function refundAdminUserId(): ?int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('admin_user'), ['user_id'])
            ->where('username = ?', 'refund_admin')
            ->limit(1);
        $id = $connection->fetchOne($select);

        return $id === false || $id === null ? null : (int) $id;
    }

    private function taxCodeForSku(string $sku): ?string
    {
        foreach (SeedData::products() as $product) {
            if ((string) $product['sku'] === $sku) {
                return $product['tax_code'] !== null ? (string) $product['tax_code'] : null;
            }
        }

        return null;
    }

    private function num(mixed $value): string
    {
        return sprintf('%.4F', (float) $value);
    }
}
