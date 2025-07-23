<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use DateTimeImmutable;
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
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStore;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultRepository::class)]
final class DefaultRepositoryTest extends TestCase
{
    public function testSaveAggregate(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                return $message->header(PlayheadHeader::class)->playhead === 1;
            });

        $repository = new DefaultRepository($store, Profile::metadata());
        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));

        $repository->save($aggregate);
    }

    public function testUpdateAggregate(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                if ($message->header(PlayheadHeader::class)->playhead === 1 && $message->event()::class === ProfileCreated::class) {
                    return true;
                }

                return $message->header(PlayheadHeader::class)->playhead === 2 && $message->event()::class === ProfileVisited::class;
            });

        $repository = new DefaultRepository($store, Profile::metadata());
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
        $store = $this->createMock(Store::class);
        $store->expects($this->exactly(2))->method('save');

        $eventBus = $this->createMock(EventBus::class);
        $eventBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                if ($message->header(PlayheadHeader::class)->playhead === 1 && $message->event()::class === ProfileCreated::class) {
                    return true;
                }

                return $message->header(PlayheadHeader::class)->playhead === 2 && $message->event()::class === ProfileVisited::class;
            });

        $repository = new DefaultRepository($store, Profile::metadata(), $eventBus);
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
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                if (!$message->hasHeader(ArchivedHeader::class)) {
                    return false;
                }

                return $message->header(PlayheadHeader::class)->playhead === 1;
            });

        $decorator = new class implements MessageDecorator {
            public function __invoke(Message $message): Message
            {
                return $message->withHeader(new ArchivedHeader());
            }
        };

        $repository = new DefaultRepository(
            $store,
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
        $store = $this->createMock(Store::class);

        $repository = new DefaultRepository(
            $store,
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
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                return $message->header(PlayheadHeader::class)->playhead === 1;
            });

        $repository = new DefaultRepository($store, Profile::metadata());
        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);
        $repository->save($aggregate);
    }

    public function testDetachedException(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->isInstanceOf(Message::class))
            ->willThrowException(new RuntimeException());

        $repository = new DefaultRepository($store, Profile::metadata());

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

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->never())
            ->method('save')
            ->with($this->isInstanceOf(Message::class));

        $repository = new DefaultRepository($store, Profile::metadata());

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

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Message::class))
            ->willThrowException(new UniqueConstraintViolation());

        $repository = new DefaultRepository($store, Profile::metadata());

        $aggregate = Profile::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $repository->save($aggregate);
    }

    public function testOutdated(): void
    {
        $this->expectException(AggregateOutdated::class);

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls(
                true,
                $this->throwException(new UniqueConstraintViolation()),
            );

        $repository = new DefaultRepository($store, Profile::metadata());

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
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'profile-1') {
                    return false;
                }

                return $message->header(PlayheadHeader::class)->playhead === 1;
            });

        $repository = new DefaultRepository(
            $store,
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
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new ArchivedCriterion(false),
            ))->willReturn(new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )
                    ->withHeader(new StreamNameHeader('profile-1'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            ]));

        $repository = new DefaultRepository($store, Profile::metadata());

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(Profile::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateTwice(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new ArchivedCriterion(false),
            ))->willReturn(
                new ArrayStream([
                    Message::create(
                        new ProfileCreated(
                            ProfileId::fromString('1'),
                            Email::fromString('hallo@patchlevel.de'),
                        ),
                    )
                        ->withHeader(new StreamNameHeader('profile-1'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                ]),
                new ArrayStream([
                    Message::create(
                        new ProfileCreated(
                            ProfileId::fromString('1'),
                            Email::fromString('hallo@patchlevel.de'),
                        ),
                    )
                        ->withHeader(new StreamNameHeader('profile-1'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                ]),
            );

        $repository = new DefaultRepository($store, Profile::metadata());

        $aggregate1 = $repository->load(ProfileId::fromString('1'));
        $aggregate2 = $repository->load(ProfileId::fromString('1'));

        self::assertEquals($aggregate1, $aggregate2);
        self::assertNotSame($aggregate1, $aggregate2);
    }

    public function testAggregateNotFound(): void
    {
        $this->expectException(AggregateNotFound::class);

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new ArchivedCriterion(false),
            ))
            ->willReturn(new ArrayStream());

        $repository = new DefaultRepository($store, Profile::metadata());

        $repository->load(ProfileId::fromString('1'));
    }

    public function testHasAggregate(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
            ))
            ->willReturn(1);

        $repository = new DefaultRepository($store, Profile::metadata());

        self::assertTrue($repository->has(ProfileId::fromString('1')));
    }

    public function testNotHasAggregate(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
            ))
            ->willReturn(0);

        $repository = new DefaultRepository($store, Profile::metadata());

        self::assertFalse($repository->has(ProfileId::fromString('1')));
    }

    public function testLoadAggregateWithSnapshot(): void
    {
        $id = ProfileId::fromString('1');

        $profile = ProfileWithSnapshot::createProfile(
            $id,
            Email::fromString('hallo@patchlevel.de'),
        );

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile_with_snapshot-1'),
                new FromPlayheadCriterion(1),
            ))
            ->willReturn(new ArrayStream());

        $snapshotStore = $this->createMock(SnapshotStore::class);
        $snapshotStore->method('load')->with(ProfileWithSnapshot::class, $id)->willReturn($profile);

        $repository = new DefaultRepository(
            $store,
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore,
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithSnapshotFirstTime(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile_with_snapshot-1'),
                new ArchivedCriterion(false),
            ))
            ->willReturn(
                new ArrayStream([
                    Message::create(
                        new ProfileCreated(
                            ProfileId::fromString('1'),
                            Email::fromString('hallo@patchlevel.de'),
                        ),
                    )
                        ->withHeader(new StreamNameHeader('profile_with_snapshot-1'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                    Message::create(
                        new ProfileVisited(
                            ProfileId::fromString('1'),
                        ),
                    )
                        ->withHeader(new StreamNameHeader('profile_with_snapshot-1'))
                        ->withHeader(new PlayheadHeader(2))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                    Message::create(
                        new ProfileVisited(
                            ProfileId::fromString('1'),
                        ),
                    )
                        ->withHeader(new StreamNameHeader('profile_with_snapshot-1'))
                        ->withHeader(new PlayheadHeader(3))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                ]),
            );

        $snapshotStore = $this->createMock(SnapshotStore::class);
        $snapshotStore
            ->expects($this->once())
            ->method('load')
            ->with(
                ProfileWithSnapshot::class,
                ProfileId::fromString('1'),
            )
            ->willThrowException(new SnapshotNotFound(
                ProfileWithSnapshot::class,
                ProfileId::fromString('1'),
            ));

        $snapshotStore
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(ProfileWithSnapshot::class));

        $repository = new DefaultRepository(
            $store,
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore,
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

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile_with_snapshot-1'),
                new FromPlayheadCriterion(1),
            ))
            ->willReturn(new ArrayStream([
                Message::create(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    ),
                )
                    ->withHeader(new StreamNameHeader('profile-1'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                Message::create(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    ),
                )
                    ->withHeader(new StreamNameHeader('profile-1'))
                    ->withHeader(new PlayheadHeader(2))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
                Message::create(
                    new ProfileVisited(
                        ProfileId::fromString('1'),
                    ),
                )
                    ->withHeader(new StreamNameHeader('profile-1'))
                    ->withHeader(new PlayheadHeader(3))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            ]));

        $snapshotStore = $this->createMock(SnapshotStore::class);
        $snapshotStore
            ->expects($this->once())
            ->method('load')
            ->with(ProfileWithSnapshot::class, ProfileId::fromString('1'))
            ->willReturn($profile);

        $snapshotStore
            ->expects($this->once())
            ->method('save')
            ->with($profile);

        $repository = new DefaultRepository(
            $store,
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore,
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(4, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testLoadAggregateWithoutSnapshot(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile_with_snapshot-1'),
                new ArchivedCriterion(false),
            ))
            ->willReturn(new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )
                    ->withHeader(new StreamNameHeader('profile-1'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            ]));

        $snapshotStore = $this->createMock(SnapshotStore::class);
        $snapshotStore
            ->expects($this->once())
            ->method('load')
            ->with(ProfileWithSnapshot::class, ProfileId::fromString('1'))
            ->willThrowException(new SnapshotNotFound(ProfileWithSnapshot::class, ProfileId::fromString('1')));

        $repository = new DefaultRepository(
            $store,
            ProfileWithSnapshot::metadata(),
            null,
            $snapshotStore,
        );

        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithSnapshot::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }

    public function testSaveAggregateInOtherStream(): void
    {
        $store = $this->createMock(StreamStore::class);
        $store
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Message $message) {
                if ($message->header(StreamNameHeader::class)->streamName !== 'other-1') {
                    return false;
                }

                return $message->header(PlayheadHeader::class)->playhead === 1;
            });

        $repository = new DefaultRepository($store, ProfileWithStream::metadata());
        $aggregate = ProfileWithStream::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $aggregate->visitProfile(ProfileId::fromString('2'));
        $repository->save($aggregate);
    }

    public function testLoadAggregateFromOtherStream(): void
    {
        $store = $this->createMock(StreamStore::class);

        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('other-1'),
                new ArchivedCriterion(false),
            ))
            ->willReturn(new ArrayStream([
                Message::create(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('hallo@patchlevel.de'),
                    ),
                )->withHeader(new StreamNameHeader('other-1'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable())),
            ]));

        $repository = new DefaultRepository($store, ProfileWithStream::metadata());
        $aggregate = $repository->load(ProfileId::fromString('1'));

        self::assertInstanceOf(ProfileWithStream::class, $aggregate);
        self::assertSame(1, $aggregate->playhead());
        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('hallo@patchlevel.de'), $aggregate->email());
    }
}
