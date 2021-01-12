<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

class InMemorySnapshotStoreTest extends TestCase
{
    public function testInMemorySnapshotStore(): void
    {
        $store = new InMemorySnapshotStore();

        $snapshot = new Snapshot(
            Profile::class,
            '1',
            0,
            ['foo' => 'bar']
        );

        $store->save($snapshot);

        self::assertEquals($snapshot, $store->load(Profile::class, '1'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $store = new InMemorySnapshotStore();
        $store->load(Profile::class, '1');
    }
}
