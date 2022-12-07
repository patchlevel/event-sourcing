<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection */
final class ProjectionCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = new ProjectionId('test', 1);

        $state = new Projection(
            $id
        );

        $collection = new ProjectionCollection([$state]);

        self::assertTrue($collection->has($id));
        self::assertSame($state, $collection->get($id));
        self::assertSame(1, $collection->count());
    }

    public function testCreateWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectionId::class);

        $id = new ProjectionId('test', 1);

        new ProjectionCollection([
            new Projection(
                $id
            ),
            new Projection(
                $id
            ),
        ]);
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $collection = new ProjectionCollection();
        /** @psalm-suppress UnusedMethodCall */
        $collection->get(new ProjectionId('test', 1));
    }

    public function testAdd(): void
    {
        $id = new ProjectionId('test', 1);

        $state = new Projection(
            $id
        );

        $collection = new ProjectionCollection();
        $newCollection = $collection->add($state);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($id));
        self::assertSame($state, $newCollection->get($id));
    }

    public function testAddWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectionId::class);

        $id = new ProjectionId('test', 1);

        /** @psalm-suppress UnusedMethodCall */
        (new ProjectionCollection())
            ->add(new Projection($id))
            ->add(new Projection($id));
    }

    public function testMinProjectorPosition(): void
    {
        $collection = new ProjectionCollection([
            new Projection(
                new ProjectionId('foo', 1),
                ProjectionStatus::Active,
                10
            ),
            new Projection(
                new ProjectionId('bar', 1),
                ProjectionStatus::Active,
                5
            ),
            new Projection(
                new ProjectionId('baz', 1),
                ProjectionStatus::Active,
                15
            ),
        ]);

        self::assertSame(5, $collection->minProjectionPosition());
    }

    public function testMinProjectorPositionWithEmptyCollection(): void
    {
        $collection = new ProjectionCollection();

        self::assertSame(0, $collection->minProjectionPosition());
    }

    public function testFilter(): void
    {
        $fooId = new ProjectionId('foo', 1);
        $barId = new ProjectionId('bar', 1);

        $collection = new ProjectionCollection([
            new Projection(
                $fooId,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $barId,
                ProjectionStatus::Active,
            ),
        ]);

        $newCollection = $collection->filter(static fn (Projection $state) => $state->isActive());

        self::assertNotSame($collection, $newCollection);
        self::assertFalse($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }

    public function testFilterByProjectStatus(): void
    {
        $fooId = new ProjectionId('foo', 1);
        $barId = new ProjectionId('bar', 1);

        $collection = new ProjectionCollection([
            new Projection(
                $fooId,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $barId,
                ProjectionStatus::Active,
            ),
        ]);

        $newCollection = $collection->filterByProjectionStatus(ProjectionStatus::Active);

        self::assertNotSame($collection, $newCollection);
        self::assertFalse($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }

    public function testFilterByCriteriaEmpty(): void
    {
        $fooId = new ProjectionId('foo', 1);
        $barId = new ProjectionId('bar', 1);

        $collection = new ProjectionCollection([
            new Projection(
                $fooId,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $barId,
                ProjectionStatus::Active,
            ),
        ]);

        $criteria = new ProjectionCriteria();

        $newCollection = $collection->filterByCriteria($criteria);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(2, $newCollection->count());
    }

    public function testFilterByCriteriaWithIds(): void
    {
        $fooId = new ProjectionId('foo', 1);
        $barId = new ProjectionId('bar', 1);

        $collection = new ProjectionCollection([
            new Projection(
                $fooId,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $barId,
                ProjectionStatus::Active,
            ),
        ]);

        $criteria = new ProjectionCriteria([$fooId]);

        $newCollection = $collection->filterByCriteria($criteria);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($fooId));
        self::assertFalse($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }
}
