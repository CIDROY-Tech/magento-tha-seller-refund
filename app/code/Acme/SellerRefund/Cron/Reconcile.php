<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Cron;

use Acme\SellerRefund\Model\Reconciler;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Periodic safety-net sweep that enqueues Read Refund Status for settled cash refunds.
 */
class Reconcile
{
    private const BATCH = 200;

    public function __construct(
        private readonly Reconciler $reconciler,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function execute(): void
    {
        $this->reconciler->run($this->timezone->date(), self::BATCH);
    }
}
