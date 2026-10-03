<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToIndexCriterion::class)]
final class ToIndexCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new ToIndexCriterion(100);

        self::assertSame(100, $object->toIndex);
    }
}
