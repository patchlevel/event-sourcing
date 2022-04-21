<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterNotFound;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore */
class DefaultSnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testNewAggregateShouldNotSaved(): void
    {
        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->save()->shouldNotBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

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

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->save('profile_with_snapshot-1', 11, ['foo' => 'bar'])->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $store->save($snapshot);
    }

    public function testNewAggregateShouldNotBeSavedTwice(): void
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

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->save('profile_with_snapshot-1', 11, ['foo' => 'bar'])->shouldBeCalled();
        $wrappedStore->save('profile_with_snapshot-1', 13, ['foo' => 'bar'])->shouldNotBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $store->save($snapshot);
        $store->save($newSnapshot);
    }

    public function testExistingAggregateShouldNotSaved(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            11,
            ['foo' => 'bar']
        );

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->load('profile_with_snapshot-1')->willReturn([11, ['foo' => 'bar']]);
        $wrappedStore->save()->shouldNotBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

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

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->load('profile_with_snapshot-1')->willReturn([11, ['foo' => 'bar']]);
        $wrappedStore->save('profile_with_snapshot-1', 25, ['foo' => 'bar'])->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

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

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->load('profile_with_snapshot-1')->willReturn([11, ['foo' => 'bar']]);
        $wrappedStore->save('profile_with_snapshot-1', 13, ['foo' => 'bar'])->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));

        $store->freeMemory();
        $store->save($newSnapshot);
    }

    public function testAdapterIsMissing(): void
    {
        $this->expectException(AdapterNotFound::class);

        $store = new DefaultSnapshotStore([]);
        $store->load(ProfileWithSnapshot::class, '1');
    }

    public function testGetAdapter(): void
    {
        $adapter = $this->prophesize(SnapshotAdapter::class)->reveal();
        $store = new DefaultSnapshotStore(['memory' => $adapter]);

        self::assertSame($adapter, $store->adapter(ProfileWithSnapshot::class));
    }
}
