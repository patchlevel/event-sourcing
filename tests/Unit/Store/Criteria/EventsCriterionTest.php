<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventsCriterion::class)]
final class EventsCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new EventsCriterion(['foo', 'bar']);

        self::assertSame(['foo', 'bar'], $object->events);
    }
}
