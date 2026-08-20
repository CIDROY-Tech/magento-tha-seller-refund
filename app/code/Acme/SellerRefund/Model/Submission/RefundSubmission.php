<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Submission;

/**
 * Immutable description of what the operator asked to refund.
 */
final class RefundSubmission
{
    /**
     * @param array<int, string> $qtyByItem order_item_id => requested quantity (4-dp string)
     */
    public function __construct(
        public readonly string $reasonCode,
        public readonly array $qtyByItem,
        public readonly ?int $actorId = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromRequest(array $data): self
    {
        $reasonCode = (string) ($data['reason_code'] ?? '');

        $qtyByItem = [];
        foreach ((array) ($data['items'] ?? []) as $itemId => $qty) {
            if ($qty === null || $qty === '') {
                continue;
            }
            $qtyByItem[(int) $itemId] = (string) $qty;
        }

        $actorId = isset($data['actor_id']) && $data['actor_id'] !== ''
            ? (int) $data['actor_id']
            : null;

        return new self($reasonCode, $qtyByItem, $actorId);
    }
}
