<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter */
class InMemorySnapshotAdapterTest extends TestCase
{
    public function testInMemorySnapshotStore(): void
    {
        $store = new InMemorySnapshotAdapter();

        $store->save('baz', 1, ['foo' => 'bar']);

        self::assertSame([1, ['foo' => 'bar']], $store->load('baz'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $store = new InMemorySnapshotAdapter();
        $store->load('baz');
    }
}
