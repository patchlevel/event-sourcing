<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionEngineCriteria::class)]
final class SubscriptionEngineCriteriaTest extends TestCase
{
    public function testInstantiate(): void
    {
        $criteria = new SubscriptionEngineCriteria(['foo'], ['bar']);

        self::assertSame(['foo'], $criteria->ids);
        self::assertSame(['bar'], $criteria->groups);
    }

    public function testInstantiateWithDefaults(): void
    {
        $criteria = new SubscriptionEngineCriteria();

        self::assertNull($criteria->ids);
        self::assertNull($criteria->groups);
    }
}
