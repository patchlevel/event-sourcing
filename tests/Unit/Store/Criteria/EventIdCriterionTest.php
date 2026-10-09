<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\EventIdCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventIdCriterion::class)]
final class EventIdCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new EventIdCriterion('foo');

        self::assertSame('foo', $object->eventId);
    }
}
