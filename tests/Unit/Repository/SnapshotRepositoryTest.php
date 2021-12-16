<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Repository\AggregateNotFound;
use Patchlevel\EventSourcing\Repository\InvalidAggregateClass;
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Repository\WrongAggregate;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
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
use stdClass;

class SnapshotRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testInstantiateWithWrongClass(): void
    {
        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $this->expectException(InvalidAggregateClass::class);
        $this->expectExceptionMessage('Class "stdClass" is not a SnapshotableAggregateRoot.');
        new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            stdClass::class,
            $snapshotStore->reveal()
        );
    }

    public function testSaveWrongAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('d.a.badura@gmail.com')
        );

        $this->expectException(WrongAggregate::class);
        $repository->save($aggregate);
    }

    public function testSaveAggregateWithEmptyEventStream(): void
    {
        $store = $this->prophesize(Store::class);
        $store->saveBatch(
            ProfileWithSnapshot::class,
            '1',
            Argument::size(1)
        )->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(AggregateChanged::class))->shouldNotBeCalled();

        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('d.a.badura@gmail.com')
        );
        $aggregate->releaseEvents();

        $repository->save($aggregate);
    }

    public function testSaveAggregate(): void
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

        $repository = new SnapshotRepository(
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

    public function testLoadAggregateWithSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            ProfileWithSnapshot::class,
            '1',
            1
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
                1,
                [
                    'id' => '1',
                    'email' => 'd.a.badura@gmail.com',
                ]
            )
        );

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertEquals(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());
    }

    public function testLoadAggregateWithoutSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(ProfileWithSnapshot::class, '1')->willReturn([
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(1),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, '1')
            ->willThrow(SnapshotNotFound::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertEquals(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());
    }

    public function testLoadAggregateCached(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn([
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(1),
        ]);

        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, '1')
            ->willThrow(SnapshotNotFound::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertEquals(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());

        self::assertSame($aggregate, $repository->load('1'));
    }

    public function testAggregateNotFound(): void
    {
        $this->expectException(AggregateNotFound::class);

        $store = $this->prophesize(Store::class);
        $store->load(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn([]);

        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, '1')
            ->willThrow(SnapshotNotFound::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $repository->load('1');
    }

    public function testHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->has(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn(true);

        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        self::assertTrue($repository->has('1'));
    }

    public function testHasAggregateCached(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(ProfileWithSnapshot::class, '1')
            ->willReturn(
                [
                    ProfileCreated::raise(
                        ProfileId::fromString('1'),
                        Email::fromString('d.a.badura@gmail.com')
                    )->recordNow(1),
                ]
            );
        $store->has(ProfileWithSnapshot::class, '1')->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, '1')
            ->willThrow(SnapshotNotFound::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertEquals(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('d.a.badura@gmail.com'), $aggregate->email());

        self::assertTrue($repository->has('1'));
    }

    public function testNotHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->has(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn(false);

        $eventBus = $this->prophesize(EventBus::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $repository = new SnapshotRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        self::assertFalse($repository->has('1'));
    }
}
