<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration\Framework;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base integration test case. Exposes the shared ObjectManager created by bootstrap.php so
 * concrete tests can resolve live store services.
 */
abstract class AppTestCase extends TestCase
{
    protected ObjectManagerInterface $objectManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = $this->sharedObjectManager();
    }

    protected function sharedObjectManager(): ObjectManagerInterface
    {
        if (isset($GLOBALS['acmeSellerRefundObjectManager'])
            && $GLOBALS['acmeSellerRefundObjectManager'] instanceof ObjectManagerInterface
        ) {
            return $GLOBALS['acmeSellerRefundObjectManager'];
        }

        return ObjectManager::getInstance();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function get(string $type): object
    {
        return $this->objectManager->get($type);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     * @param array<string, mixed> $arguments
     *
     * @return T
     */
    protected function create(string $type, array $arguments = []): object
    {
        return $this->objectManager->create($type, $arguments);
    }
}
