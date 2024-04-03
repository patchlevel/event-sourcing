<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\AggregateAlreadyExists;
use Patchlevel\EventSourcing\Repository\AggregateDetached;
use Patchlevel\EventSourcing\Repository\AggregateNotFound;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Repository\AggregateUnknown;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\WrongAggregate;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\ArchivableStore;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Throwable;

/** @covers \Patchlevel\EventSourcing\Repository\DefaultRepository */
final class DefaultRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
        )->shouldBeCalled();

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testUpdateAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalled();

        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
        )->shouldBeCalled();

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testEventBus(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalled();

        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
        )->shouldBeCalled();

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalled();

        $eventBus->dispatch(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
        )->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
            $eventBus->reveal(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testDecorator(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                if (!$message->hasHeader(ArchivedHeader::class)) {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalled();

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $decorator = new class implements MessageDecorator {
            public function __invoke(Message $message): Message
            {
                return $message->withHeader(new ArchivedHeader());
            }
        };

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
            null,
            null,
            $decorator,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);
    }

    public function testSaveWrongAggregate(): void
    {
        $store = $this->prophesize(Store::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $this->expectException(WrongAggregate::class);

        /** @psalm-suppress InvalidArgument */
        $repository->save($aggregate);
    }

    public function testSaveAggregateWithEmptyEventStream(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalledOnce();

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);
        $repository->save($aggregate);
    }

    public function testDetachedException(): void
    {
        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::type(Message::class),
        )->willThrow(new RuntimeException());

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        try {
            $repository->save($aggregate);
        } catch (Throwable) {
            // do nothing
        }

        $this->expectException(AggregateDetached::class);

        $repository->save($aggregate);
    }

    public function testUnknownException(): void
    {
        $this->expectException(AggregateUnknown::class);

        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::type(Message::class),
        )->shouldNotBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $aggregate->releaseEvents();

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testDuplicate(): void
    {
        $this->expectException(AggregateAlreadyExists::class);

        $store = $this->prophesize(Store::class);
        $store->save(
            Argument::type(Message::class),
        )->willThrow(new UniqueConstraintViolation());

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);
    }

    public function testOutdated(): void
    {
        $this->expectException(AggregateOutdated::class);

        $store = $this->prophesize(Store::class);

        $store->save(
            Argument::that(static function (Message $message) {
                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
        )->shouldBeCalled();

        $store->save(
            Argument::that(static function (Message $message) {
                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
        )->willThrow(new UniqueConstraintViolation());

        $store->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testSaveAggregateWithSplitStream(): void
    {
        $store = $this->prophesize(Store::class);
        $store->willImplement(ArchivableStore::class);
        $store->save(
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 1;
            }),
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 2;
            }),
            Argument::that(static function (Message $message) {
                if ($message->header(AggregateHeader::class)->aggregateName !== 'profile') {
                    return false;
                }

                if ($message->header(AggregateHeader::class)->aggregateId !== '1') {
                    return false;
                }

                return $message->header(AggregateHeader::class)->playhead === 3;
            }),
        )->shouldBeCalled();
        $store->archiveMessages('profile', '1', 3)->shouldBeCalledOnce();
        $store->transactional(Argument::any())->will(
            /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
            null,
            null,
            new SplitStreamDecorator(new AttributeEventMetadataFactory()),
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );
        $aggregate->visitProfile(ProfileId::fromString('2'));
        $aggregate->splitIt();

        $repository->save($aggregate);
    }

    public function testLoadAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(
            'profile',
            '1',
        ))->willReturn(new ArrayStream([
            Message::create(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
        ]));

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(Profile::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateTwice(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(
            'profile',
            '1',
        ))->willReturn(
            new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
            ]),
            new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
            ]),
        );

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $aggregate1 = $repository->load(ProfileId::fromString('1'));
        $aggregate2 = $repository->load(ProfileId::fromString('1'));

        self::assertEquals($aggregate1, $aggregate2);
        self::assertNotSame($aggregate1, $aggregate2);
    }

    public function testAggregateNotFound(): void
    {
        $this->expectException(AggregateNotFound::class);

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(
            'profile',
            '1',
        ))->willReturn(new ArrayStream());

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        $repository->load(ProfileId::fromString('1'));
    }

    public function testHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->count(new Criteria(
            'profile',
            '1',
        ))->willReturn(1);

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        self::assertTrue($repository->has(ProfileId::fromString('1')));
    }

    public function testNotHasAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $store->count(new Criteria(
            'profile',
            '1',
        ))->willReturn(0);

        $repository = new DefaultRepository(
            $store->reveal(),
            Profile::metadata(),
        );

        self::assertFalse($repository->has(ProfileId::fromString('1')));
    }

    public function testLoadAggregateWithSnapshot(): void
    {
        $id = ProfileId::fromString('1');

        $profile = ProfileWithSnapshot::createProfile(
            $id,
            Email::fromString('hallo@patchlevel.de'),
        );

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(
            'profile_with_snapshot',
            '1',
            null,
            1,
        ))->willReturn(new ArrayStream());

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(
            ProfileWithSnapshot::class,
            $id,
        )->willReturn($profile);

        $repository = new DefaultRepository(
            $store->reveal(),
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore->reveal(),
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithSnapshotFirstTime(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(
            new Criteria(
                'profile_with_snapshot',
                '1',
            ),
        )->willReturn(
            new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
                Message::create(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 2, new DateTimeImmutable())),
                Message::create(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 3, new DateTimeImmutable())),
            ]),
        );

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(
            ProfileWithSnapshot::class,
            ProfileId::fromString('1'),
        )->willThrow(new SnapshotNotFound(ProfileWithSnapshot::class, ProfileId::fromString('1')));

        $snapshotStore->save(Argument::type(ProfileWithSnapshot::class))->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore->reveal(),
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(3, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithSnapshotAndSaveNewVersion(): void
    {
        $profile = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $store = $this->prophesize(Store::class);
        $store->load(
            new Criteria(
                'profile_with_snapshot',
                '1',
                null,
                1,
            ),
        )->willReturn(new ArrayStream([
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 2, new DateTimeImmutable())),
            Message::create(
                new ProfileVisited(
                    ProfileId::fromString('1'),
                ),
            )->withHeader(new AggregateHeader('profile', '1', 3, new DateTimeImmutable())),
        ]));

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(
            ProfileWithSnapshot::class,
            ProfileId::fromString('1'),
        )->willReturn($profile);

        $snapshotStore->save($profile)->shouldBeCalled();

        $repository = new DefaultRepository(
            $store->reveal(),
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore->reveal(),
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(4, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithoutSnapshot(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(new Criteria('profile_with_snapshot', '1'))
            ->willReturn(new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable())),
            ]));

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $snapshotStore->load(ProfileWithSnapshot::class, ProfileId::fromString('1'))
            ->willThrow(SnapshotNotFound::class);

        $repository = new DefaultRepository(
            $store->reveal(),
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore->reveal(),
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }
}
