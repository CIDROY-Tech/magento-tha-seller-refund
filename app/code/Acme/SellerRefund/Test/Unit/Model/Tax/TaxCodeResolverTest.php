<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model\Tax;

use Acme\SellerRefund\Model\Tax\TaxCodeResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the tax-code resolver. The rate table and code validation are
 * constant-driven and touch no database; the option-id mapping is exercised against a mocked
 * resource connection whose fetchPairs returns a deliberately shuffled option-id -> code map.
 */
class TaxCodeResolverTest extends TestCase
{
    private ResourceConnection&MockObject $resource;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceConnection::class);
    }

    public function testRateForKnownCodes(): void
    {
        $resolver = new TaxCodeResolver($this->resource);

        self::assertSame('0.0000', $resolver->rateFor('999'));
        self::assertSame('0.1000', $resolver->rateFor('010'));
        self::assertSame('0.0800', $resolver->rateFor('008'));
    }

    public function testAssertKnownAcceptsKnownAndRejectsUnknown(): void
    {
        $resolver = new TaxCodeResolver($this->resource);

        $resolver->assertKnown('010');
        $this->addToAssertionCount(1);

        $this->expectException(\InvalidArgumentException::class);
        $resolver->assertKnown('777');
    }

    public function testRateForUnknownCodeThrows(): void
    {
        $resolver = new TaxCodeResolver($this->resource);

        $this->expectException(\InvalidArgumentException::class);
        $resolver->rateFor('777');
    }

    public function testToBusinessCodeAndToOptionIdUseTheMappedShuffle(): void
    {
        // Option ids (5/6/7) deliberately differ from the numeric business codes.
        $this->primeConnection([
            5 => '010',
            6 => '008',
            7 => '999',
        ]);
        $resolver = new TaxCodeResolver($this->resource);

        self::assertSame('010', $resolver->toBusinessCode(5));
        self::assertSame('008', $resolver->toBusinessCode(6));
        self::assertSame('999', $resolver->toBusinessCode(7));

        self::assertSame(5, $resolver->toOptionId('010'));
        self::assertSame(6, $resolver->toOptionId('008'));
        self::assertSame(7, $resolver->toOptionId('999'));

        // Proves the option id is not the same as the business code (the seed shuffle).
        self::assertNotSame((int) '010', $resolver->toOptionId('010'));
        self::assertNotSame((int) '999', $resolver->toOptionId('999'));
    }

    public function testToBusinessCodeThrowsForUnmappedOptionId(): void
    {
        $this->primeConnection([5 => '010']);
        $resolver = new TaxCodeResolver($this->resource);

        $this->expectException(\InvalidArgumentException::class);
        $resolver->toBusinessCode(999);
    }

    /**
     * @param array<int, string> $pairs option_id => business code
     */
    private function primeConnection(array $pairs): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->with($select)->willReturn($pairs);

        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnArgument(0);
    }
}
