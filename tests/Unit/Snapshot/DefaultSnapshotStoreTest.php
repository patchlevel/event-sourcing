<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterNotFound;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore */
final class DefaultSnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $adapter = $this->prophesize(SnapshotAdapter::class);
        $adapter->save(
            'profile_with_snapshot-1',
            [
                'version' => '1',
                'payload' => ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2],
            ],
        )->shouldBeCalled();

        $store = new DefaultSnapshotStore(['memory' => $adapter->reveal()]);

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $store->save($aggregate);
    }

    public function testLoad(): void
    {
        $adapter = $this->prophesize(SnapshotAdapter::class);
        $adapter->load(
            'profile_with_snapshot-1',
        )->willReturn(
            [
                'version' => '1',
                'payload' => ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2],
            ],
        );

        $store = new DefaultSnapshotStore(['memory' => $adapter->reveal()]);

        $aggregate = $store->load(ProfileWithSnapshot::class, '1');

        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('info@patchlevel.de'), $aggregate->email());
        self::assertEquals(2, $aggregate->playhead());
    }

    public function testLoadLegacySnapshots(): void
    {
        $this->expectException(SnapshotVersionInvalid::class);

        $adapter = $this->prophesize(SnapshotAdapter::class);
        $adapter->load(
            'profile_with_snapshot-1',
        )->willReturn(['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2]);

        $store = new DefaultSnapshotStore(['memory' => $adapter->reveal()]);

        $store->load(ProfileWithSnapshot::class, '1');
    }

    public function testLoadExpiredSnapshot(): void
    {
        $this->expectException(SnapshotVersionInvalid::class);

        $adapter = $this->prophesize(SnapshotAdapter::class);
        $adapter->load(
            'profile_with_snapshot-1',
        )->willReturn(
            [
                'version' => '2',
                'payload' => ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 2],
            ],
        );

        $store = new DefaultSnapshotStore(['memory' => $adapter->reveal()]);

        $store->load(ProfileWithSnapshot::class, '1');
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
