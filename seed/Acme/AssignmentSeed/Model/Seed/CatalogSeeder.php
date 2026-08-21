<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Creates the seller and first-party products used by the seeded orders. Seller products carry
 * an mp_tax_class option id; the single first-party product carries none.
 */
class CatalogSeeder
{
    private const ATTRIBUTE_SET_DEFAULT = 4;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TaxAttributeSeeder $taxAttributeSeeder
    ) {
    }

    /**
     * @return array<string, int> sku => created count contribution (0 or 1); used for the summary
     */
    public function seed(): array
    {
        $optionMap = $this->taxAttributeSeeder->loadOptionMap();
        $store = $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore(1);
        $websiteId = (int) $store->getWebsiteId();

        $created = [];
        foreach (SeedData::products() as $product) {
            $sku = (string) $product['sku'];
            if ($this->exists($sku)) {
                $created[$sku] = 0;
                continue;
            }

            /** @var ProductInterface $model */
            $model = $this->productFactory->create();
            $model->setSku($sku);
            $model->setName((string) $product['name']);
            $model->setAttributeSetId(self::ATTRIBUTE_SET_DEFAULT);
            $model->setTypeId(Type::TYPE_SIMPLE);
            $model->setPrice((float) $product['price']);
            $model->setVisibility(Visibility::VISIBILITY_BOTH);
            $model->setStatus(Status::STATUS_ENABLED);
            $model->setWeight(1);
            $model->setTaxClassId(0);
            $model->setWebsiteIds([$websiteId]);
            $model->setStockData([
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 1000,
            ]);

            if ($product['is_seller'] === true && $product['tax_code'] !== null) {
                $code = (string) $product['tax_code'];
                if (isset($optionMap[$code])) {
                    $model->setData(SeedData::ATTRIBUTE_CODE, $optionMap[$code]);
                }
            }

            $this->productRepository->save($model);
            $created[$sku] = 1;
        }

        return $created;
    }

    public function reset(): void
    {
        foreach (SeedData::products() as $product) {
            $sku = (string) $product['sku'];
            if (!$this->exists($sku)) {
                continue;
            }
            try {
                $this->productRepository->deleteById($sku);
            } catch (NoSuchEntityException) {
                // Already gone; nothing to do.
            }
        }
    }

    private function exists(string $sku): bool
    {
        try {
            $this->productRepository->get($sku);

            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }
}
