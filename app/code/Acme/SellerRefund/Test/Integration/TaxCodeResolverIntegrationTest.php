<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Model\Tax\TaxCodeResolver;
use Acme\SellerRefund\Test\Integration\Framework\AppTestCase;

/**
 * The tax-code resolver maps between real EAV option ids and business codes at store scope 0,
 * and the seeded option ids are deliberately shuffled so an option id never equals its code.
 */
class TaxCodeResolverIntegrationTest extends AppTestCase
{
    public function testBusinessCodesRoundTripThroughRealEavOptionIds(): void
    {
        $resolver = $this->get(TaxCodeResolver::class);

        $optionIds = [];
        foreach (['999', '010', '008'] as $code) {
            $optionId = $resolver->toOptionId($code);
            self::assertSame($code, $resolver->toBusinessCode($optionId));
            $optionIds[$code] = $optionId;
        }

        // The tax-exempt option id is not literally 999: option ids are EAV auto-increment
        // values, distinct from the business code (proves the seed shuffle).
        self::assertNotSame(999, $optionIds['999']);

        // The three option ids are distinct.
        self::assertCount(3, array_unique($optionIds));
    }
}
