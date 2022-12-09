<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria */
final class ProjectionCriteriaTest extends TestCase
{
    public function testProjectionId(): void
    {
        $id = new ProjectionId('test', 1);
        $criteria = new ProjectionCriteria([$id]);

        self::assertEquals([$id], $criteria->ids);
    }
}
