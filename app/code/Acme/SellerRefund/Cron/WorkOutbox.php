<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Cron;

use Acme\SellerRefund\Model\Outbox\Dispatcher;

/**
 * Drains the refund outbox once per minute across all operations.
 */
class WorkOutbox
{
    private const LIMIT = 50;

    public function __construct(
        private readonly Dispatcher $dispatcher
    ) {
    }

    public function execute(): void
    {
        $this->dispatcher->run(null, self::LIMIT);
    }
}
