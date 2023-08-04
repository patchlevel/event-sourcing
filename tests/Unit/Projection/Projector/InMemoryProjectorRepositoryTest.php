<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository */
final class InMemoryProjectorRepositoryTest extends TestCase
{
    public function testGetAllProjectorsIsEmpty(): void
    {
        $repository = new InMemoryProjectorRepository();
        self::assertCount(0, $repository->projectors());
    }

    public function testGetAllProjectors(): void
    {
        $projector = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('dummy', 1);
            }
        };
        $repository = new InMemoryProjectorRepository([$projector]);

        self::assertEquals([$projector], $repository->projectors());
    }
}
