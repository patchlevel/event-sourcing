<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter */
final class InMemorySnapshotAdapterTest extends TestCase
{
    public function testInMemorySnapshotStore(): void
    {
        $store = new InMemorySnapshotAdapter();

        $store->save('baz', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $store->load('baz'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $store = new InMemorySnapshotAdapter();
        $store->load('baz');
    }

    public function testClear(): void
    {
        $store = new InMemorySnapshotAdapter();
        $store->save('baz', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $store->load('baz'));
        $store->clear();

        $this->expectException(SnapshotNotFound::class);
        $store->load('baz');
    }
}
