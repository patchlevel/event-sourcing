<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection */
final class ProjectionCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = 'test';
        $projection = new Projection($id);
        $collection = new ProjectionCollection([$projection]);

        self::assertTrue($collection->has($id));
        self::assertSame($projection, $collection->get($id));
        self::assertSame(1, $collection->count());
    }

    public function testCreateWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectionId::class);

        $id = 'test';

        new ProjectionCollection([
            new Projection($id),
            new Projection($id),
        ]);
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $collection = new ProjectionCollection();
        /** @psalm-suppress UnusedMethodCall */
        $collection->get('test');
    }

    public function testAdd(): void
    {
        $id = 'test';
        $projection = new Projection($id);

        $collection = new ProjectionCollection();
        $newCollection = $collection->add($projection);

        self::assertNotSame($collection, $newCollection);
        self::assertTrue($newCollection->has($id));
        self::assertSame($projection, $newCollection->get($id));
    }

    public function testAddWithDuplicatedId(): void
    {
        $this->expectException(DuplicateProjectionId::class);

        $id = 'test';

        /** @psalm-suppress UnusedMethodCall */
        (new ProjectionCollection())
            ->add(new Projection($id))
            ->add(new Projection($id));
    }

    public function testLowestProjectionPosition(): void
    {
        $collection = new ProjectionCollection([
            new Projection(
                'foo',
                ProjectionStatus::Active,
                10,
            ),
            new Projection(
                'bar',
                ProjectionStatus::Active,
                5,
            ),
            new Projection(
                'baz',
                ProjectionStatus::Active,
                15,
            ),
        ]);

        self::assertSame(5, $collection->getLowestProjectionPosition());
    }

    public function testLowestProjectionPositionWithEmptyCollection(): void
    {
        $collection = new ProjectionCollection();

        self::assertSame(0, $collection->getLowestProjectionPosition());
    }

    public function testFilter(): void
    {
        $fooId = 'foo';
        $barId = 'bar';

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

        $newCollection = $collection->filter(static fn (Projection $projection) => $projection->isActive());

        self::assertNotSame($collection, $newCollection);
        self::assertFalse($newCollection->has($fooId));
        self::assertTrue($newCollection->has($barId));
        self::assertSame(1, $newCollection->count());
    }

    public function testFilterByProjectStatus(): void
    {
        $fooId = 'foo';
        $barId = 'bar';

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
        $fooId = 'foo';
        $barId = 'bar';

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
        $fooId = 'foo';
        $barId = 'bar';

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

    public function testIterator(): void
    {
        $id = 'test';
        $projection = new Projection($id);
        $collection = new ProjectionCollection([$projection]);

        $iterator = $collection->getIterator();

        self::assertSame($projection, $iterator->current());
        self::assertSame(1, $iterator->count());
    }
}
