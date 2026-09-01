<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Unit\Model\Erp;

use Acme\SellerRefund\Model\Config;
use Acme\SellerRefund\Model\Erp\BackoffPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the exponential backoff policy. The config is mocked to base 5,
 * cap 300.
 */
class BackoffPolicyTest extends TestCase
{
    private BackoffPolicy $policy;

    protected function setUp(): void
    {
        /** @var Config&MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('backoffBase')->willReturn(5);
        $config->method('backoffCap')->willReturn(300);
        $this->policy = new BackoffPolicy($config);
    }

    public function testDelayGrowsExponentially(): void
    {
        self::assertSame(5, $this->policy->delaySeconds(1));
        self::assertSame(25, $this->policy->delaySeconds(2));
        self::assertSame(125, $this->policy->delaySeconds(3));
    }

    public function testDelayIsCappedAtTheConfiguredCap(): void
    {
        // 5 ** 4 = 625, capped to 300.
        self::assertSame(300, $this->policy->delaySeconds(4));
        self::assertSame(300, $this->policy->delaySeconds(6));
    }

    public function testRetryAfterIsHonouredWhenLarger(): void
    {
        // Base delay for attempt 1 is 5; a larger Retry-After wins.
        self::assertSame(30, $this->policy->delaySeconds(1, 30));
    }

    public function testRetryAfterIsIgnoredWhenSmaller(): void
    {
        // Base delay for attempt 3 is 125; a smaller Retry-After does not shorten it.
        self::assertSame(125, $this->policy->delaySeconds(3, 10));
    }
}
