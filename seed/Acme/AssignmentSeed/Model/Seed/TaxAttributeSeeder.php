<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Creates the mp_tax_class product select attribute and populates its options.
 *
 * The three option values stored at store scope 0 are the business tax codes 999, 010 and
 * 008. The option ids that back them are assigned by the database auto-increment: a throwaway
 * option is created and deleted first (so the ids do not start at a clean 1..3 boundary), then
 * the three codes are inserted in a randomised order. The resulting option id is therefore
 * neither equal to the numeric business code nor aligned with the declaration order, and it
 * differs from one environment to the next. TaxCodeResolver maps option id to the store-0
 * value, so correctness depends on the stored value being the business code, never the id.
 */
class TaxAttributeSeeder
{
    private const THROWAWAY_VALUE = 'SEED_TMP_DELETE_ME';

    public function __construct(
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array<string, int> business code => assigned option id
     */
    public function seed(): array
    {
        $this->ensureAttribute();

        $existing = $this->loadOptionMap();
        if ($this->hasAllCodes($existing)) {
            return $existing;
        }

        $attributeId = $this->attributeId();
        $connection = $this->resource->getConnection();
        $optionTable = $this->resource->getTableName('eav_attribute_option');
        $valueTable = $this->resource->getTableName('eav_attribute_option_value');

        // 1) Create then immediately delete a throwaway option to advance the auto-increment
        //    so the real option ids are not a tidy 1..3 sequence.
        $connection->insert($optionTable, ['attribute_id' => $attributeId, 'sort_order' => 0]);
        $throwawayId = (int) $connection->lastInsertId($optionTable);
        $connection->insert(
            $valueTable,
            ['option_id' => $throwawayId, 'store_id' => 0, 'value' => self::THROWAWAY_VALUE]
        );
        $connection->delete($valueTable, ['option_id = ?' => $throwawayId]);
        $connection->delete($optionTable, ['option_id = ?' => $throwawayId]);

        // 2) Insert the business codes in a randomised order.
        $codes = SeedData::TAX_CODES;
        shuffle($codes);

        $sortOrder = 10;
        foreach ($codes as $code) {
            $connection->insert($optionTable, ['attribute_id' => $attributeId, 'sort_order' => $sortOrder]);
            $optionId = (int) $connection->lastInsertId($optionTable);
            $connection->insert(
                $valueTable,
                ['option_id' => $optionId, 'store_id' => 0, 'value' => $code]
            );
            $sortOrder += 10;
        }

        return $this->loadOptionMap();
    }

    public function reset(): void
    {
        $attributeId = $this->attributeIdOrNull();
        if ($attributeId === null) {
            return;
        }

        $connection = $this->resource->getConnection();
        $optionTable = $this->resource->getTableName('eav_attribute_option');
        // Deleting the option rows cascades to eav_attribute_option_value.
        $connection->delete($optionTable, ['attribute_id = ?' => $attributeId]);
    }

    /**
     * @return array<string, int> business code => option id, for the store-0 values
     */
    public function loadOptionMap(): array
    {
        $attributeId = $this->attributeIdOrNull();
        if ($attributeId === null) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['oav' => $this->resource->getTableName('eav_attribute_option_value')], ['oav.value', 'oav.option_id'])
            ->join(
                ['o' => $this->resource->getTableName('eav_attribute_option')],
                'o.option_id = oav.option_id',
                []
            )
            ->where('o.attribute_id = ?', $attributeId)
            ->where('oav.store_id = ?', 0);

        $map = [];
        foreach ($connection->fetchPairs($select) as $value => $optionId) {
            $map[(string) $value] = (int) $optionId;
        }

        return $map;
    }

    private function ensureAttribute(): void
    {
        if ($this->attributeIdOrNull() !== null) {
            return;
        }

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(
            Product::ENTITY,
            SeedData::ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Seller Tax Class',
                'input' => 'select',
                'source' => Table::class,
                'required' => false,
                'user_defined' => true,
                'global' => 1,
                'visible' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'General',
            ]
        );
    }

    private function attributeId(): int
    {
        $id = $this->attributeIdOrNull();
        if ($id === null) {
            throw new \RuntimeException('The mp_tax_class attribute could not be created.');
        }

        return $id;
    }

    private function attributeIdOrNull(): ?int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['a' => $this->resource->getTableName('eav_attribute')], ['a.attribute_id'])
            ->join(
                ['et' => $this->resource->getTableName('eav_entity_type')],
                'et.entity_type_id = a.entity_type_id',
                []
            )
            ->where('a.attribute_code = ?', SeedData::ATTRIBUTE_CODE)
            ->where('et.entity_type_code = ?', Product::ENTITY);

        $id = $connection->fetchOne($select);

        return $id === false || $id === null ? null : (int) $id;
    }

    /**
     * @param array<string, int> $map
     */
    private function hasAllCodes(array $map): bool
    {
        foreach (SeedData::TAX_CODES as $code) {
            if (!isset($map[$code])) {
                return false;
            }
        }

        return true;
    }
}
