<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStore\InMemoryStore;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorStore\InMemoryStore */
class InMemoryStoreTest extends TestCase
{
    public function testSave(): void
    {
        $store = new InMemoryStore();

        $id = new ProjectorId('test', 1);

        $state = new ProjectorState(
            $id
        );

        $store->saveProjectorState($state);

        self::assertEquals($state, $store->getProjectorState($id));

        $collection = $store->getStateFromAllProjectors();

        self::assertTrue($collection->has($id));
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectorStateNotFound::class);

        $store = new InMemoryStore();
        $store->getProjectorState(new ProjectorId('test', 1));
    }

    public function testRemove(): void
    {
        $store = new InMemoryStore();

        $id = new ProjectorId('test', 1);

        $state = new ProjectorState(
            $id
        );

        $store->saveProjectorState($state);

        $collection = $store->getStateFromAllProjectors();

        self::assertTrue($collection->has($id));

        $store->removeProjectorState($id);

        $collection = $store->getStateFromAllProjectors();

        self::assertFalse($collection->has($id));
    }
}
