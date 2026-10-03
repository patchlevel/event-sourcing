<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionCriteria::class)]
final class SubscriptionCriteriaTest extends TestCase
{
    public function testInstantiate(): void
    {
        $criteria = new SubscriptionCriteria(['foo'], ['bar'], [Status::Active]);

        self::assertSame(['foo'], $criteria->ids);
        self::assertSame(['bar'], $criteria->groups);
        self::assertSame([Status::Active], $criteria->status);
    }

    public function testInstantiateWithDefaults(): void
    {
        $criteria = new SubscriptionCriteria();

        self::assertNull($criteria->ids);
        self::assertNull($criteria->groups);
        self::assertNull($criteria->status);
    }
}
