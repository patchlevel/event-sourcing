<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToPlayheadCriterion::class)]
final class ToPlayheadCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new ToPlayheadCriterion(10);

        self::assertSame(10, $object->toPlayhead);
    }
}
