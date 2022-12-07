<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\DefaultProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\StatefulProjector;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\DefaultProjectorRepository */
final class DefaultProjectorRepositoryTest extends TestCase
{
    public function testGetAllProjectorsIsEmpty(): void
    {
        $repository = new DefaultProjectorRepository();
        self::assertCount(0, $repository->projectors());
    }

    public function testGetAllProjectors(): void
    {
        $projector = new class implements Projector {
        };
        $repository = new DefaultProjectorRepository([$projector]);

        self::assertEquals([$projector], $repository->projectors());
    }

    public function testGetAllStatefulProjectors(): void
    {
        $projector = new class implements Projector {
        };

        $statefulProjector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $repository = new DefaultProjectorRepository([$projector, $statefulProjector]);

        self::assertEquals([$statefulProjector], $repository->statefulProjectors());
    }

    public function testFindProjectorByProjectionId(): void
    {
        $projector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $repository = new DefaultProjectorRepository([$projector]);

        self::assertSame($projector, $repository->findByProjectionId(new ProjectionId('test', 1)));
    }

    public function testProjectorNotFound(): void
    {
        $repository = new DefaultProjectorRepository();

        self::assertNull($repository->findByProjectionId(new ProjectionId('test', 1)));
    }
}
