<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Export;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Erp\PayloadBuilder;

/**
 * Legacy settlement adjustment export. Each refunded line carries its per-line tax code and
 * amount as produced by the payload builder, so the settlement figures line up with what the
 * ERP was sent at Create Refund time.
 */
class SettlementAdjustmentExporter
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly PayloadBuilder $payloadBuilder
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(RefundInterface $refund): array
    {
        $lines = [];
        foreach ($this->refundRepository->getItems((int) $refund->getEntityId()) as $item) {
            $lines[] = [
                'sku' => $item->getSku(),
                'quantity' => (int) $item->getQtyRefund(),
                'row_amount' => $item->getRowAmount(),
                'shipping_amount' => $item->getShippingAmount(),
                'taxes' => $this->payloadBuilder->buildTaxes($item),
            ];
        }

        return [
            'refund_no' => $refund->getRefundNo(),
            'seller_order_id' => $refund->getSellerOrderId(),
            'currency' => $refund->getCurrencyCode(),
            'grand_total' => $refund->getGrandTotal(),
            'lines' => $lines,
        ];
    }
}
