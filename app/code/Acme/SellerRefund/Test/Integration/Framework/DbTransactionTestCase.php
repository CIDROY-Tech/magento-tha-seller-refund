<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration\Framework;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Base case that isolates every test in a database transaction.
 *
 * setUp() opens a transaction on the shared default connection; tearDown() rolls it back, so
 * nothing a test writes survives. RefundProcessor::submit() opens its own transaction and
 * commits it, but Magento's Pdo\Mysql adapter counts nested transactions: submit()'s
 * beginTransaction only raises the level and its commit only lowers it, so the outer rollback
 * here still undoes the refund, its items, events and outbox rows.
 */
abstract class DbTransactionTestCase extends AppTestCase
{
    private AdapterInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->get(ResourceConnection::class)->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    protected function connection(): AdapterInterface
    {
        return $this->connection;
    }
}
