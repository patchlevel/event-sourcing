<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FromPlayheadCriterion::class)]
final class FromPlayheadCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new FromPlayheadCriterion(1);

        self::assertSame(1, $object->fromPlayhead);
    }
}
