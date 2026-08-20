<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Erp;

final class CreateResult
{
    public function __construct(
        public readonly string $erpRefundId,
        public readonly string $status
    ) {
    }

    public function getErpRefundId(): string
    {
        return $this->erpRefundId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
