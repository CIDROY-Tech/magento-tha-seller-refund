<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Outbox;

use Acme\SellerRefund\Model\Config;
use Acme\SellerRefund\Model\Erp\BackoffPolicy;
use Acme\SellerRefund\Model\Erp\Exception\ErpTransientException;
use Acme\SellerRefund\Service\RefundProcessor;
use Psr\Log\LoggerInterface;

/**
 * Claims outbox jobs and drives the matching processor step. Transient failures are
 * rescheduled with backoff up to the configured attempt cap; anything else is dead-lettered.
 */
class Dispatcher
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly RefundProcessor $processor,
        private readonly BackoffPolicy $backoffPolicy,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function run(?string $op, int $limit): int
    {
        $operations = $op !== null
            ? [$op]
            : [Outbox::OP_CREATE, Outbox::OP_STATUS_CHECK, Outbox::OP_CONFIRM];

        $processed = 0;
        foreach ($operations as $operation) {
            foreach ($this->outbox->claim($operation, $limit) as $row) {
                $this->handle($operation, $row);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function handle(string $op, array $row): void
    {
        $id = (int) $row['entity_id'];
        $refundId = (int) $row['refund_id'];
        $attempts = (int) $row['attempts'];
        $nextAttempt = $attempts + 1;

        try {
            switch ($op) {
                case Outbox::OP_CREATE:
                    $this->processor->process($refundId, $nextAttempt);
                    break;
                case Outbox::OP_STATUS_CHECK:
                    $this->processor->checkStatus($refundId);
                    break;
                case Outbox::OP_CONFIRM:
                    $this->processor->confirm($refundId);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown outbox operation "%s".', $op));
            }
            $this->outbox->succeed($id);
        } catch (ErpTransientException $e) {
            $this->outbox->fail($id, $e->getMessage());
            if ($nextAttempt >= $this->config->maxAttempts()) {
                $this->outbox->dead($id);
                return;
            }
            $this->outbox->reschedule($id, $this->backoffPolicy->delaySeconds($nextAttempt, $e->getRetryAfter()));
        } catch (\Throwable $e) {
            $this->outbox->fail($id, $e->getMessage());
            $this->outbox->dead($id);
            $this->logger->error(
                'Refund outbox job failed: ' . $e->getMessage(),
                ['refund_id' => $refundId, 'operation' => $op]
            );
        }
    }
}
