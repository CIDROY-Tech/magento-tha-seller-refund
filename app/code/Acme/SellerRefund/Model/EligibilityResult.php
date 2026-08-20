<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

final class EligibilityResult
{
    /**
     * @param string[] $reasons
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly array $reasons = []
    ) {
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }
}
