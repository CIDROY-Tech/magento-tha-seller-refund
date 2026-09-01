<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration\Framework;

use Acme\SellerRefund\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Thin client for the ERP Refund API stub's debug surface. The base URL is taken from
 * ERP_STUB_BASE_URL when set, otherwise from the module configuration (erp/base_url).
 */
class ErpStub
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config
    ) {
    }

    public function reset(): void
    {
        $this->post('/_debug/reset', '');
    }

    public function setFailmode(string $mode, ?int $times = null): void
    {
        $body = ['mode' => $mode];
        if ($times !== null) {
            $body['times'] = $times;
        }

        $this->post('/erp-api/v1/_debug/failmode', (string) json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    public function getRefunds(): array
    {
        $curl = $this->newCurl();
        $curl->get($this->baseUrl() . '/_debug/refunds');
        $decoded = json_decode($curl->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function uniqueRefunds(): int
    {
        return (int) ($this->getRefunds()['unique_refunds'] ?? 0);
    }

    private function post(string $path, string $body): void
    {
        $curl = $this->newCurl();
        $curl->post($this->baseUrl() . $path, $body);
    }

    private function newCurl(): Curl
    {
        $curl = $this->curlFactory->create();
        $curl->addHeader('Content-Type', 'application/json');

        return $curl;
    }

    private function baseUrl(): string
    {
        $env = getenv('ERP_STUB_BASE_URL');
        $base = $env !== false && $env !== '' ? $env : $this->config->erpBaseUrl();

        return rtrim($base, '/');
    }
}
