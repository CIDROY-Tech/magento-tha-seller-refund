<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Total;

/**
 * The full set of figures a refund renders on every surface: the original pre-refund seller
 * totals and the refund totals, plus the per-line refund breakdown.
 */
final class RefundFigures
{
    /**
     * @param RefundFigureLine[] $lines
     */
    public function __construct(
        public readonly string $preRefundSubtotal,
        public readonly string $preRefundShipping,
        public readonly string $preRefundTax,
        public readonly string $preRefundGrandTotal,
        public readonly string $refundSubtotal,
        public readonly string $refundShipping,
        public readonly string $refundTax,
        public readonly string $refundGrandTotal,
        public readonly array $lines,
        public readonly string $currency,
        public readonly bool $partial
    ) {
    }

    public function isPartial(): bool
    {
        return $this->partial;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'is_partial' => $this->partial,
            'pre_refund' => [
                'subtotal' => $this->preRefundSubtotal,
                'shipping' => $this->preRefundShipping,
                'tax' => $this->preRefundTax,
                'grand_total' => $this->preRefundGrandTotal,
            ],
            'refund' => [
                'subtotal' => $this->refundSubtotal,
                'shipping' => $this->refundShipping,
                'tax' => $this->refundTax,
                'grand_total' => $this->refundGrandTotal,
            ],
            'lines' => array_map(static fn (RefundFigureLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}
