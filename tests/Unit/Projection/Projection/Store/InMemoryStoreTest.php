<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionAlreadyExists;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore */
final class InMemoryStoreTest extends TestCase
{
    public function testAdd(): void
    {
        $store = new InMemoryStore();

        $id = 'test';
        $projection = new Projection($id);

        $store->add($projection);

        self::assertEquals($projection, $store->get($id));
        self::assertEquals([$projection], $store->find());
    }

    public function testAddDuplicated(): void
    {
        $this->expectException(ProjectionAlreadyExists::class);

        $id = 'test';
        $projection = new Projection($id);

        $store = new InMemoryStore([$projection]);
        $store->add($projection);
    }

    public function testUpdate(): void
    {
        $id = 'test';
        $projection = new Projection($id);

        $store = new InMemoryStore([$projection]);

        $store->update($projection);

        self::assertEquals($projection, $store->get($id));
        self::assertEquals([$projection], $store->find());
    }

    public function testUpdateNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $id = 'test';
        $projection = new Projection($id);

        $store = new InMemoryStore();

        $store->update($projection);
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $store = new InMemoryStore();
        $store->get('test');
    }

    public function testRemove(): void
    {
        $id = 'test';
        $projection = new Projection($id);

        $store = new InMemoryStore([$projection]);

        $store->remove($projection);

        self::assertEquals([], $store->find());
    }

    public function testFind(): void
    {
        $projection1 = new Projection('1');
        $projection2 = new Projection('2');

        $store = new InMemoryStore([$projection1, $projection2]);

        self::assertEquals([$projection1, $projection2], $store->find());
    }

    public function testFindById(): void
    {
        $projection1 = new Projection('1');
        $projection2 = new Projection('2');

        $store = new InMemoryStore([$projection1, $projection2]);

        $criteria = new ProjectionCriteria(
            ids: ['1'],
        );

        self::assertEquals([$projection1], $store->find($criteria));
    }

    public function testFindByGroup(): void
    {
        $projection1 = new Projection('1', group: 'group1');
        $projection2 = new Projection('2', group: 'group2');

        $store = new InMemoryStore([$projection1, $projection2]);

        $criteria = new ProjectionCriteria(
            groups: ['group1'],
        );

        self::assertEquals([$projection1], $store->find($criteria));
    }

    public function testFindByStatus(): void
    {
        $projection1 = new Projection('1', status: ProjectionStatus::New);
        $projection2 = new Projection('2', status: ProjectionStatus::Booting);

        $store = new InMemoryStore([$projection1, $projection2]);

        $criteria = new ProjectionCriteria(
            status: [ProjectionStatus::New],
        );

        self::assertEquals([$projection1], $store->find($criteria));
    }

    public function testFindByAll(): void
    {
        $projection1 = new Projection('1', group: 'group1', status: ProjectionStatus::New);
        $projection2 = new Projection('2', group: 'group2', status: ProjectionStatus::Booting);

        $store = new InMemoryStore([$projection1, $projection2]);

        $criteria = new ProjectionCriteria(
            ids: ['1'],
            groups: ['group1'],
            status: [ProjectionStatus::New],
        );

        self::assertEquals([$projection1], $store->find($criteria));
    }
}
