<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp\Exception;

use Throwable;

/**
 * A definitive business rejection from the ERP. Terminal: the refund moves to failed
 * and requires a new business decision.
 */
class ErpBusinessException extends ErpException
{
    public function __construct(
        string $message,
        private readonly string $erpErrorCode = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErpErrorCode(): string
    {
        return $this->erpErrorCode;
    }
}
