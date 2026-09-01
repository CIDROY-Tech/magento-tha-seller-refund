<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model\Export;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\Data\RefundItemInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Erp\PayloadBuilder;
use Acme\SellerRefund\Model\Export\SettlementAdjustmentExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the legacy settlement export. The payload builder is mocked to return
 * the simple per-line tax shape, so this test asserts only the exporter's own mapping and is
 * independent of the payload builder's real signature.
 */
class SettlementAdjustmentExporterTest extends TestCase
{
    public function testExportMapsPayloadBuilderTaxesOntoEachLine(): void
    {
        $refund = $this->createMock(RefundInterface::class);
        $refund->method('getEntityId')->willReturn(42);
        $refund->method('getRefundNo')->willReturn('SR-20260907-000123');
        $refund->method('getSellerOrderId')->willReturn('SO-1001');
        $refund->method('getCurrencyCode')->willReturn('JPY');
        $refund->method('getGrandTotal')->willReturn('2640.0000');

        $item = $this->createMock(RefundItemInterface::class);
        $item->method('getSku')->willReturn('SELLER-RED-01');
        $item->method('getQtyRefund')->willReturn('2');
        $item->method('getRowAmount')->willReturn('2400.0000');
        $item->method('getShippingAmount')->willReturn('0.0000');

        $repository = $this->createMock(RefundRepositoryInterface::class);
        $repository->method('getItems')->with(42)->willReturn([$item]);

        // The builder is mocked with the simple per-line tax shape regardless of its real
        // signature; the exporter must map whatever buildTaxes returns straight through.
        $payloadBuilder = $this->createMock(PayloadBuilder::class);
        $payloadBuilder->method('buildTaxes')->willReturn([
            ['code' => '010', 'amount' => '120.0000'],
        ]);

        $exporter = new SettlementAdjustmentExporter($repository, $payloadBuilder);

        $result = $exporter->export($refund);

        self::assertSame('SR-20260907-000123', $result['refund_no']);
        self::assertSame('SO-1001', $result['seller_order_id']);
        self::assertSame('JPY', $result['currency']);
        self::assertSame('2640.0000', $result['grand_total']);
        self::assertCount(1, $result['lines']);
        self::assertSame([
            'sku' => 'SELLER-RED-01',
            'quantity' => 2,
            'row_amount' => '2400.0000',
            'shipping_amount' => '0.0000',
            'taxes' => [['code' => '010', 'amount' => '120.0000']],
        ], $result['lines'][0]);
    }
}
