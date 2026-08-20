<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp\Exception;

use Throwable;

/**
 * A recoverable ERP failure: timeout, curl error, HTTP 408/429/5xx, or an eventually
 * consistent 404 on Read Refund Status. The refund holds its main status and retries.
 */
class ErpTransientException extends ErpException
{
    public function __construct(
        string $message,
        private readonly int $httpCode = 0,
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
