<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Tax;

use Magento\Framework\App\ResourceConnection;

/**
 * Maps between the local mp_tax_class attribute option ids and the business tax codes the
 * ERP expects. The business codes are the admin option values at store scope 0; option ids
 * are environment-specific and must never leave the store.
 */
class TaxCodeResolver
{
    private const ATTRIBUTE_CODE = 'mp_tax_class';
    private const PRODUCT_ENTITY_TYPE = 'catalog_product';

    private const RATES = [
        '999' => '0.0000',
        '010' => '0.1000',
        '008' => '0.0800',
    ];

    /**
     * @var array<int, string>|null option_id => business code
     */
    private ?array $codeByOptionId = null;

    /**
     * @var array<string, int>|null business code => option_id
     */
    private ?array $optionIdByCode = null;

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function toBusinessCode(int $optionId): string
    {
        $this->load();
        if (!isset($this->codeByOptionId[$optionId])) {
            throw new \InvalidArgumentException(
                sprintf('No mp_tax_class business code is mapped to option id %d.', $optionId)
            );
        }

        return $this->codeByOptionId[$optionId];
    }

    public function toOptionId(string $code): int
    {
        $this->load();
        if (!isset($this->optionIdByCode[$code])) {
            throw new \InvalidArgumentException(
                sprintf('No mp_tax_class option is mapped to business code "%s".', $code)
            );
        }

        return $this->optionIdByCode[$code];
    }

    public function rateFor(string $code): string
    {
        $this->assertKnown($code);

        return self::RATES[$code];
    }

    public function assertKnown(string $code): void
    {
        if (!isset(self::RATES[$code])) {
            throw new \InvalidArgumentException(sprintf('Unknown business tax code "%s".', $code));
        }
    }

    private function load(): void
    {
        if ($this->codeByOptionId !== null) {
            return;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['oav' => $this->resource->getTableName('eav_attribute_option_value')], ['oav.option_id', 'oav.value'])
            ->join(
                ['o' => $this->resource->getTableName('eav_attribute_option')],
                'o.option_id = oav.option_id',
                []
            )
            ->join(
                ['a' => $this->resource->getTableName('eav_attribute')],
                'a.attribute_id = o.attribute_id',
                []
            )
            ->join(
                ['et' => $this->resource->getTableName('eav_entity_type')],
                'et.entity_type_id = a.entity_type_id',
                []
            )
            ->where('a.attribute_code = ?', self::ATTRIBUTE_CODE)
            ->where('et.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
            ->where('oav.store_id = ?', 0);

        $this->codeByOptionId = [];
        $this->optionIdByCode = [];
        foreach ($connection->fetchPairs($select) as $optionId => $value) {
            $optionId = (int) $optionId;
            $value = (string) $value;
            $this->codeByOptionId[$optionId] = $value;
            $this->optionIdByCode[$value] = $optionId;
        }
    }
}
