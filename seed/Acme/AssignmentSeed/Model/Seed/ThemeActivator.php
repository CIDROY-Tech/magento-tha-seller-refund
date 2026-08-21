<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;

/**
 * Activates the Acme/blank frontend theme if it is registered. When the theme is not yet
 * present the activation is skipped gracefully.
 */
class ThemeActivator
{
    private const CONFIG_THEME_ID = 'design/theme/theme_id';

    public function __construct(
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function activate(): ?string
    {
        $themeId = $this->findThemeId();
        if ($themeId === null) {
            return null;
        }

        $this->configWriter->save(self::CONFIG_THEME_ID, (string) $themeId, 'default', 0);

        return SeedData::THEME_CODE;
    }

    private function findThemeId(): ?int
    {
        $collection = $this->themeCollectionFactory->create();
        $collection->addFieldToFilter('area', SeedData::THEME_AREA);
        $collection->addFieldToFilter('code', SeedData::THEME_CODE);
        $collection->setPageSize(1);
        $theme = $collection->getFirstItem();

        return $theme->getId() ? (int) $theme->getId() : null;
    }
}
