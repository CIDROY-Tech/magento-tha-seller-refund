<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp\Exception;

/**
 * The ERP reported a conflicting state for the idempotency key (HTTP 409), for example
 * a materially different payload sent under an already-used refund_no.
 */
class ErpConflictException extends ErpException
{
}
