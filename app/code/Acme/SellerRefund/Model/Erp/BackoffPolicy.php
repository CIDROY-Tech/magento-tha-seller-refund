<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp;

use Acme\SellerRefund\Model\Config;

/**
 * Exponential backoff for retriable ERP operations, capped, and never shorter than a
 * server-supplied Retry-After.
 */
class BackoffPolicy
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function delaySeconds(int $attempt, ?int $retryAfter = null): int
    {
        $attempt = max(1, $attempt);
        $base = max(1, $this->config->backoffBase());
        $cap = max($base, $this->config->backoffCap());

        $delay = (int) min($cap, $base ** $attempt);

        if ($retryAfter !== null) {
            $delay = max($delay, $retryAfter);
        }

        return $delay;
    }
}
