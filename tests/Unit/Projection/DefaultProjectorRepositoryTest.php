<?php

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\DefaultProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\DefaultProjectorRepository */
class DefaultProjectorRepositoryTest extends TestCase
{
    public function testGetAllProjectorsIsEmpty(): void
    {
        $repository = new DefaultProjectorRepository();
        self::assertCount(0, $repository->projectors());
    }

    public function testGetAllProjectors(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }
        };

        $repository = new DefaultProjectorRepository([$projector]);

        self::assertEquals([$projector], $repository->projectors());
    }

    public function testFindProjector(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }
        };

        $repository = new DefaultProjectorRepository([$projector]);

        self::assertSame($projector, $repository->findByProjectorId(new ProjectorId('test', 1)));
    }

    public function testProjectorNotFound(): void
    {
        $repository = new DefaultProjectorRepository();

        self::assertNull($repository->findByProjectorId(new ProjectorId('test', 1)));
    }
}