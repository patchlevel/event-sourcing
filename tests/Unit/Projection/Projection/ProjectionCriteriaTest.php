<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria */
final class ProjectionCriteriaTest extends TestCase
{
    public function testProjectorId(): void
    {
        $id = new ProjectionId('test', 1);

        $projectorId = new ProjectionCriteria(
            [$id],
        );

        self::assertEquals([$id], $projectorId->ids);
    }
}
