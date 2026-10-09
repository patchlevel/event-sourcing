<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchivedCriterion::class)]
final class ArchivedCriterionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new ArchivedCriterion(true);

        self::assertSame(true, $object->archived);
    }
}
