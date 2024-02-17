<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore */
final class InMemoryStoreTest extends TestCase
{
    public function testSave(): void
    {
        $store = new InMemoryStore();

        $id = 'test';
        $projection = new Projection($id);

        $store->save($projection);

        self::assertEquals($projection, $store->get($id));

        $collection = $store->all();

        self::assertTrue($collection->has($id));
    }

    public function testNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $store = new InMemoryStore();
        $store->get('test');
    }

    public function testRemove(): void
    {
        $store = new InMemoryStore();

        $id = 'test';
        $projection = new Projection($id);

        $store->save($projection);

        $collection = $store->all();

        self::assertTrue($collection->has($id));

        $store->remove($id);

        $collection = $store->all();

        self::assertFalse($collection->has($id));
    }
}
