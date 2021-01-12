<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Repository\AggregateNotFoundException;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

use function assert;

class RepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->saveBatch(
            Profile::class,
            '1',
            Argument::size(1)
        )->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(AggregateChanged::class))->shouldBeCalled();

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('d.a.badura@gmail.com')
        );

        $repository->save($aggregate);
    }

    public function testSaveAggregateWithSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->saveBatch(
            ProfileWithSnapshot::class,
            '1',
            Argument::size(1)
        )->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(AggregateChanged::class))->shouldBeCalled();

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->save(Argument::type(Snapshot::class))->shouldBeCalled();

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('d.a.badura@gmail.com')
        );

        $repository->save($aggregate);
    }

    public function testLoadAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            Profile::class,
            '1'
        )->willReturn([
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = $repository->load('1');
        assert($aggregate instanceof Profile);

        self::assertEquals(0, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());
    }

    public function testLoadAggregateWithSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            ProfileWithSnapshot::class,
            '1',
            0
        )->willReturn([]);

        $eventBus = $this->prophesize(EventBus::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn(
            new Snapshot(
                ProfileWithSnapshot::class,
                '1',
                0,
                [
                    'id' => '1',
                    'email' => 'd.a.badura@gmail.com',
                ]
            )
        );

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertEquals(0, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());
    }

    public function testAggregateNotFound(): void
    {
        $this->expectException(AggregateNotFoundException::class);

        $store = $this->prophesize(Store::class);
        $store->load(
            Profile::class,
            '1'
        )->willReturn([]);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $repository->load('1');
    }

    public function testHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->has(
            Profile::class,
            '1'
        )->willReturn(true);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        self::assertTrue($repository->has('1'));
    }

    public function testNotHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->has(
            Profile::class,
            '1'
        )->willReturn(false);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new Repository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        self::assertFalse($repository->has('1'));
    }
}
