<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Acme\SellerRefund\Model\ResourceModel\Sequence;
use DateTimeInterface;

/**
 * Allocates the human-readable refund number SR-YYYYMMDD-NNNNNN in date sequence.
 */
class RefundNumberGenerator
{
    public function __construct(
        private readonly Sequence $sequence
    ) {
    }

    public function next(DateTimeInterface $now): string
    {
        $dateKey = $now->format('Ymd');

        return sprintf('SR-%s-%06d', $dateKey, $this->sequence->next($dateKey));
    }
}
