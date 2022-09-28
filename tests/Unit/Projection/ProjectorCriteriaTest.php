<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorCriteria */
class ProjectorCriteriaTest extends TestCase
{
    public function testProjectorId(): void
    {
        $projectorId = new ProjectorCriteria(
            ['test'],
        );

        self::assertEquals(['test'], $projectorId->names);
    }
}
