<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterNotFound;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
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

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $store->save($aggregate);
    }

    public function testNewAggregateShouldBeSaved(): void
    {
        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->save(
            'profile_with_snapshot-1',
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        )->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $store->save($aggregate);
    }

    public function testNewAggregateShouldNotBeSavedTwice(): void
    {
        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->save(
            'profile_with_snapshot-1',
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        )->shouldBeCalled();

        $wrappedStore->save(
            'profile_with_snapshot-1',
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 3]
        )->shouldNotBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $store->save($aggregate);

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $store->save($aggregate);
    }

    public function testExistingAggregateShouldNotSaved(): void
    {
        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->load('profile_with_snapshot-1')->willReturn(
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        );
        $wrappedStore->save()->shouldNotBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $aggregate = $store->load(ProfileWithSnapshot::class, '1');

        self::assertSame(2, $aggregate->playhead());

        $store->save($aggregate);
    }

    public function testExistingAggregateShouldBeSaved(): void
    {
        $wrappedStore = $this->prophesize(SnapshotAdapter::class);
        $wrappedStore->load('profile_with_snapshot-1')->willReturn(
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        );
        $wrappedStore->save(
            'profile_with_snapshot-1',
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 4]
        )->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $aggregate = $store->load(ProfileWithSnapshot::class, '1');

        $aggregate->visitProfile(ProfileId::fromString('2'));
        $aggregate->visitProfile(ProfileId::fromString('2'));

        $store->save($aggregate);
    }

    public function testFreeMemory(): void
    {
        $wrappedStore = $this->prophesize(SnapshotAdapter::class);

        $wrappedStore->load('profile_with_snapshot-1')->willReturn(
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        );
        $wrappedStore->save(
            'profile_with_snapshot-1',
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]
        )->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $wrappedStore->reveal()]);

        $aggregate = $store->load(ProfileWithSnapshot::class, '1');

        $store->freeMemory();
        $store->save($aggregate);
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
