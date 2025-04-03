<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionEngineCriteria::class)]
final class SubscriptionCriteriaTest extends TestCase
{
    public function testSubscriptionId(): void
    {
        $id = 'test';
        $criteria = new SubscriptionEngineCriteria([$id]);

        self::assertEquals([$id], $criteria->ids);
    }
}
