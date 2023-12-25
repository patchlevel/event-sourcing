<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
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
        $projector = new class {
        };
        $repository = new InMemoryProjectorRepository([$projector]);

        self::assertEquals([$projector], $repository->projectors());
    }
}
