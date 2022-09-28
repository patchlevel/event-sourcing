<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Patchlevel\EventSourcing\Projection\ProjectorStore\DuplicateProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection */
class ProjectorStateCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = new ProjectorId('test', 1);

        $state = new ProjectorState(
            $id
        );

        $collection = new ProjectorStateCollection([$state]);

        self::assertTrue($collection->has($id));
        self::assertSame($state, $collection->get($id));
        self::assertSame(1, $collection->count());
    }

    public function testCreateWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectorId::class);

        $id = new ProjectorId('test', 1);

        new ProjectorStateCollection([
            new ProjectorState(
                $id
            ),
            new ProjectorState(
                $id
            ),
        ]);
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectorStateNotFound::class);

        $collection = new ProjectorStateCollection();
        $collection->get(new ProjectorId('test', 1));
    }

    public function testAdd(): void
    {
        $id = new ProjectorId('test', 1);

        $state = new ProjectorState(
            $id
        );

        $collection = new ProjectorStateCollection();
        $newCollection = $collection->add($state);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($id));
        self::assertSame($state, $newCollection->get($id));
    }

    public function testAddWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectorId::class);

        $id = new ProjectorId('test', 1);

        (new ProjectorStateCollection())
            ->add(new ProjectorState($id))
            ->add(new ProjectorState($id));
    }

    public function testMinProjectorPosition(): void
    {
        $collection = new ProjectorStateCollection([
            new ProjectorState(
                new ProjectorId('foo', 1),
                ProjectorStatus::Active,
                10
            ),
            new ProjectorState(
                new ProjectorId('bar', 1),
                ProjectorStatus::Active,
                5
            ),
            new ProjectorState(
                new ProjectorId('baz', 1),
                ProjectorStatus::Active,
                15
            ),
        ]);

        self::assertSame(5, $collection->minProjectorPosition());
    }

    public function testMinProjectorPositionWithEmptyCollection(): void
    {
        $collection = new ProjectorStateCollection();

        self::assertSame(0, $collection->minProjectorPosition());
    }

    public function testFilterByProjectStatus(): void
    {
        $fooId = new ProjectorId('foo', 1);
        $barId = new ProjectorId('bar', 1);

        $collection = new ProjectorStateCollection([
            new ProjectorState(
                $fooId,
                ProjectorStatus::Booting,
            ),
            new ProjectorState(
                $barId,
                ProjectorStatus::Active,
            ),
        ]);

        $newCollection = $collection->filterByProjectorStatus(ProjectorStatus::Active);

        self::assertNotSame($collection, $newCollection);
        self::assertFalse($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }

    public function testFilterByCriteriaEmpty(): void
    {
        $fooId = new ProjectorId('foo', 1);
        $barId = new ProjectorId('bar', 1);

        $collection = new ProjectorStateCollection([
            new ProjectorState(
                $fooId,
                ProjectorStatus::Booting,
            ),
            new ProjectorState(
                $barId,
                ProjectorStatus::Active,
            ),
        ]);

        $criteria = new ProjectorCriteria();

        $newCollection = $collection->filterByCriteria($criteria);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(2, $newCollection->count());
    }

    public function testFilterByCriteriaWithIds(): void
    {
        $fooId = new ProjectorId('foo', 1);
        $barId = new ProjectorId('bar', 1);

        $collection = new ProjectorStateCollection([
            new ProjectorState(
                $fooId,
                ProjectorStatus::Booting,
            ),
            new ProjectorState(
                $barId,
                ProjectorStatus::Active,
            ),
        ]);

        $criteria = new ProjectorCriteria([$fooId]);

        $newCollection = $collection->filterByCriteria($criteria);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($fooId));
        self::assertFalse($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }
}
