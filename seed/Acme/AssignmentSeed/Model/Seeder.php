<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model;

use Acme\AssignmentSeed\Model\Seed\AdminUserSeeder;
use Acme\AssignmentSeed\Model\Seed\CatalogSeeder;
use Acme\AssignmentSeed\Model\Seed\CustomerSeeder;
use Acme\AssignmentSeed\Model\Seed\OrderSeeder;
use Acme\AssignmentSeed\Model\Seed\RefundSeeder;
use Acme\AssignmentSeed\Model\Seed\SeedData;
use Acme\AssignmentSeed\Model\Seed\TaxAttributeSeeder;
use Acme\AssignmentSeed\Model\Seed\ThemeActivator;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;

/**
 * Orchestrates the assignment seed steps under adminhtml area emulation.
 *
 * The whole run is idempotent: every step checks for its own data before creating it, so
 * running the command twice does not duplicate anything. A reset removes only the records this
 * seeder owns and then re-seeds from scratch.
 */
class Seeder
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly State $appState,
        private readonly TaxAttributeSeeder $taxAttributeSeeder,
        private readonly CatalogSeeder $catalogSeeder,
        private readonly CustomerSeeder $customerSeeder,
        private readonly AdminUserSeeder $adminUserSeeder,
        private readonly OrderSeeder $orderSeeder,
        private readonly RefundSeeder $refundSeeder,
        private readonly ThemeActivator $themeActivator,
        private readonly WriterInterface $configWriter
    ) {
    }

    /**
     * @return array<string, mixed> a summary of what was seeded
     */
    public function run(bool $reset): array
    {
        return $this->appState->emulateAreaCode(
            Area::AREA_ADMINHTML,
            fn (): array => $this->execute($reset)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(bool $reset): array
    {
        if ($reset) {
            $this->reset();
        }

        $this->taxAttributeSeeder->seed();
        $products = $this->catalogSeeder->seed();
        $customerId = $this->customerSeeder->seed();
        $adminUsernames = $this->adminUserSeeder->seed();
        $createdOrders = $this->orderSeeder->seed($customerId);
        $createdRefunds = $this->refundSeeder->seed();
        $theme = $this->themeActivator->activate();
        $version = $this->writeMarker();

        return [
            'version' => $version,
            'products_total' => count($products),
            'products_created' => array_sum($products),
            'customer_email' => SeedData::CUSTOMER_EMAIL,
            'admin_usernames' => $adminUsernames,
            'orders_present' => SeedData::orderIncrementIds(),
            'orders_created' => $createdOrders,
            'refunds_created' => $createdRefunds,
            'theme' => $theme,
        ];
    }

    /**
     * Removes only seeder-owned records, in reverse dependency order.
     */
    private function reset(): void
    {
        // Refunds must go before their orders even though the order FK would cascade.
        $this->refundSeeder->reset();
        $this->orderSeeder->reset();
        $this->customerSeeder->reset();
        $this->adminUserSeeder->reset();
        $this->catalogSeeder->reset();
        $this->taxAttributeSeeder->reset();
        $this->configWriter->delete(SeedData::CONFIG_MARKER_PATH, 'default', 0);
    }

    private function writeMarker(): string
    {
        $version = getenv('ASSIGNMENT_SEED_VERSION') ?: self::VERSION;
        $this->configWriter->save(SeedData::CONFIG_MARKER_PATH, $version, 'default', 0);

        return $version;
    }
}
