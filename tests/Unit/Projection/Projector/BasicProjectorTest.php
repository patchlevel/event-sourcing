<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\BasicProjector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectionAttributeNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\BasicProjector */
final class BasicProjectorTest extends TestCase
{
    public function testProjectionId(): void
    {
        $projector = new #[Projection('test', 1)]
        class extends BasicProjector {
        };

        self::assertEquals(
            new ProjectionId('test', 1),
            $projector->targetProjection(),
        );
    }

    public function testMissingProjectionId(): void
    {
        $this->expectException(ProjectionAttributeNotFound::class);

        $projector = new class extends BasicProjector {
        };

        $projector->targetProjection();
    }
}
