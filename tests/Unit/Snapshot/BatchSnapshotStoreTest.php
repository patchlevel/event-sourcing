<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\BatchSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class BatchSnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testNewAggregateShouldNotSaved(): void
    {
        $wrappedStore = $this->prophesize(SnapshotStore::class);
        $wrappedStore->save()->shouldNotBeCalled();

        $store = new BatchSnapshotStore($wrappedStore->reveal());

        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            ['foo' => 'bar']
        );

        $store->save($snapshot);
    }

    public function testNewAggregateShouldBeSaved(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            11,
            ['foo' => 'bar']
        );

        $wrappedStore = $this->prophesize(SnapshotStore::class);
        $wrappedStore->save($snapshot)->shouldBeCalled();

        $store = new BatchSnapshotStore($wrappedStore->reveal());

        $store->save($snapshot);
    }

    public function testExistingAggregateShouldNotSaved(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            11,
            ['foo' => 'bar']
        );

        $wrappedStore = $this->prophesize(SnapshotStore::class);
        $wrappedStore->load(ProfileWithSnapshot::class, '1')->willReturn($snapshot);
        $wrappedStore->save()->shouldNotBeCalled();

        $store = new BatchSnapshotStore($wrappedStore->reveal());

        $newSnapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            13,
            ['foo' => 'bar']
        );

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));

        $store->save($newSnapshot);
    }

    public function testExistingAggregateShouldBeSaved(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            11,
            ['foo' => 'bar']
        );

        $newSnapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            25,
            ['foo' => 'bar']
        );

        $wrappedStore = $this->prophesize(SnapshotStore::class);
        $wrappedStore->load(ProfileWithSnapshot::class, '1')->willReturn($snapshot);
        $wrappedStore->save($newSnapshot)->shouldBeCalled();

        $store = new BatchSnapshotStore($wrappedStore->reveal());

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));

        $store->save($newSnapshot);
    }

    public function testFreeMemory(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            11,
            ['foo' => 'bar']
        );

        $newSnapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            13,
            ['foo' => 'bar']
        );

        $wrappedStore = $this->prophesize(SnapshotStore::class);
        $wrappedStore->load(ProfileWithSnapshot::class, '1')->willReturn($snapshot);
        $wrappedStore->save($newSnapshot)->shouldBeCalled();

        $store = new BatchSnapshotStore($wrappedStore->reveal());

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));

        $store->freeMemory();
        $store->save($newSnapshot);
    }
}
