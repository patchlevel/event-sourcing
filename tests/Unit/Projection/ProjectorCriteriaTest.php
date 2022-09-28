<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorCriteria */
class ProjectorCriteriaTest extends TestCase
{
    public function testProjectorId(): void
    {
        $id = new ProjectorId('test', 1);

        $projectorId = new ProjectorCriteria(
            [$id],
        );

        self::assertEquals([$id], $projectorId->ids);
    }
}
