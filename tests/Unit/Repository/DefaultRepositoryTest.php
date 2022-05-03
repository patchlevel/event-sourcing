<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Repository\AggregateNotFound;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\WrongAggregate;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Repository\DefaultRepository */
class DefaultRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::type(Message::class)
        )->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

        $repository->save($aggregate);
    }

    public function testSaveWrongAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $eventBus = $this->prophesize(EventBus::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

        $this->expectException(WrongAggregate::class);

        /** @psalm-suppress InvalidArgument */
        $repository->save($aggregate);
    }

    public function testSaveAggregateWithEmptyEventStream(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::type(Message::class)
        )->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type('object'))->shouldNotBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );
        $aggregate->releaseMessages();

        $repository->save($aggregate);
    }

    public function testLoadAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            Profile::class,
            '1'
        )->willReturn([
            new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(Profile::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateTwice(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            Profile::class,
            '1'
        )->willReturn([
            new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        $aggregate1 = $repository->load('1');
        $aggregate2 = $repository->load('1');

        self::assertEquals($aggregate1, $aggregate2);
        self::assertNotSame($aggregate1, $aggregate2);
    }

    public function testAggregateNotFound(): void
    {
        $this->expectException(AggregateNotFound::class);

        $store = $this->prophesize(Store::class);
        $store->load(
            Profile::class,
            '1'
        )->willReturn([]);

        $eventBus = $this->prophesize(EventBus::class);

        $repository = new DefaultRepository(
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

        $repository = new DefaultRepository(
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

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            Profile::class,
        );

        self::assertFalse($repository->has('1'));
    }

    public function testLoadAggregateWithSnapshot(): void
    {
        $profile = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

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
        )->willReturn($profile);

        //$snapshotStore->save($profile)->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithSnapshotAndSaveNewVersion(): void
    {
        $profile = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

        $store = $this->prophesize(Store::class);
        $store->load(
            ProfileWithSnapshot::class,
            '1',
            1
        )->willReturn([
            new Message(
                ProfileWithSnapshot::class,
                '1',
                2,
                new ProfileVisited(
                    ProfileId::fromString('1'),
                )
            ),
            new Message(
                ProfileWithSnapshot::class,
                '1',
                3,
                new ProfileVisited(
                    ProfileId::fromString('1'),
                )
            ),
            new Message(
                ProfileWithSnapshot::class,
                '1',
                4,
                new ProfileVisited(
                    ProfileId::fromString('1'),
                )
            ),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(
            ProfileWithSnapshot::class,
            '1'
        )->willReturn($profile);

        $snapshotStore->save($profile)->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(4, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithoutSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(ProfileWithSnapshot::class, '1')->willReturn([
            new Message(
                ProfileWithSnapshot::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
        ]);

        $eventBus = $this->prophesize(EventBus::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, '1')
            ->willThrow(SnapshotNotFound::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            $eventBus->reveal(),
            ProfileWithSnapshot::class,
            $snapshotStore->reveal()
        );

        $aggregate = $repository->load('1');

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }
}
