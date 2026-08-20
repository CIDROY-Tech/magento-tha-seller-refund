<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the acme_seller_refund configuration tree.
 */
class Config
{
    private const XML_WINDOW_DAYS = 'acme_seller_refund/general/window_days';
    private const XML_ERP_BASE_URL = 'acme_seller_refund/erp/base_url';
    private const XML_ERP_CONNECT_TIMEOUT = 'acme_seller_refund/erp/connect_timeout';
    private const XML_ERP_TIMEOUT = 'acme_seller_refund/erp/timeout';
    private const XML_ERP_MAX_ATTEMPTS = 'acme_seller_refund/erp/max_attempts';
    private const XML_ERP_BACKOFF_BASE = 'acme_seller_refund/erp/backoff_base_seconds';
    private const XML_ERP_BACKOFF_CAP = 'acme_seller_refund/erp/backoff_cap_seconds';
    private const XML_EMAIL_TEMPLATE = 'acme_seller_refund/email/confirmation_template';
    private const XML_EMAIL_IDENTITY = 'acme_seller_refund/email/confirmation_identity';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function windowDays(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_WINDOW_DAYS, $storeId);
    }

    public function erpBaseUrl(?int $storeId = null): string
    {
        return rtrim((string) $this->value(self::XML_ERP_BASE_URL, $storeId), '/');
    }

    public function connectTimeout(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_ERP_CONNECT_TIMEOUT, $storeId);
    }

    public function timeout(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_ERP_TIMEOUT, $storeId);
    }

    public function maxAttempts(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_ERP_MAX_ATTEMPTS, $storeId);
    }

    public function backoffBase(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_ERP_BACKOFF_BASE, $storeId);
    }

    public function backoffCap(?int $storeId = null): int
    {
        return (int) $this->value(self::XML_ERP_BACKOFF_CAP, $storeId);
    }

    public function emailTemplate(?int $storeId = null): string
    {
        return (string) $this->value(self::XML_EMAIL_TEMPLATE, $storeId);
    }

    public function emailIdentity(?int $storeId = null): string
    {
        return (string) $this->value(self::XML_EMAIL_IDENTITY, $storeId);
    }

    private function value(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
