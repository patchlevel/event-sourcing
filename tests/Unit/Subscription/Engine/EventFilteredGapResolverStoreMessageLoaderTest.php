<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use ArrayIterator;
use DateInterval;
use DateTimeImmutable;
use IteratorAggregate;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredGapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\UnexpectedError;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

use function iterator_to_array;

#[CoversClass(EventFilteredGapResolverStoreMessageLoader::class)]
final class EventFilteredGapResolverStoreMessageLoaderTest extends TestCase
{
    public function testNothingToLoad(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new ArrayStream([
                5 => Message::create(new ProfileVisited(ProfileId::fromString('5')))->withHeader(new RecordedOnHeader($recordedOn)),
            ]));
        $store
            ->expects($this->never())
            ->method('count');

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(5, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([], $indexes);
    }

    public function testRangeWithoutHolesIsLoadedUpToLastIndexWithoutGapChecks(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited']), new ToIndexCriterion(5)))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(5)))
            ->willReturn(4);

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testFilteredOutEventsAreNoGap(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(5)))->evaluate($criteria, '', true) => 3,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => 0,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testMissingIndexIsFilled(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(4))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(1), new EventsCriterion(['profile_visited']), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => new ArrayStream([
                    2 => Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(3)))->evaluate($criteria, '', true) => 0,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2, 3], $indexes);
    }

    public function testSubscribedEventWrittenAfterLoadIsNotSkipped(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(4))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(1), new EventsCriterion(['profile_visited']), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => new ArrayStream([
                    2 => Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(4))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(3)))->evaluate($criteria, '', true) => 1,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(3), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => 1,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2, 3], $indexes);
    }

    public function testPermanentGapIsAcceptedAfterRetriesAndRestIsLoadedWithoutGapChecks(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        // index 2 is missing permanently, index 4 and 5 are filtered out
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(8))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    6 => Message::create(new ProfileVisited(ProfileId::fromString('6')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                    6 => Message::create(new ProfileVisited(ProfileId::fromString('6')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(1), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                    6 => Message::create(new ProfileVisited(ProfileId::fromString('6')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(3), new EventsCriterion(['profile_visited']), new ToIndexCriterion(7)))->evaluate($criteria, '', true) => new ArrayStream([
                    6 => Message::create(new ProfileVisited(ProfileId::fromString('6')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(7))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(7)))->evaluate($criteria, '', true) => 5,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(7)))->evaluate($criteria, '', true) => 4,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(3)))->evaluate($criteria, '', true) => 0,
                self::equalTo(new Criteria(new FromIndexCriterion(3), new ToIndexCriterion(7)))->evaluate($criteria, '', true) => 3,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
            [0, 0],
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 3, 6], $indexes);
    }

    public function testGapOutsideDetectionWindowIsNotChecked(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))
            ->willReturn(2);

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn->add(new DateInterval('PT10M'))),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 3], $indexes);
    }

    public function testCatchAllSubscriberTreatsEveryJumpAsGap(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(0)))
            ->willReturnOnConsecutiveCalls(
                new ArrayStream([
                    2 => Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    2 => Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
            );
        $store
            ->expects($this->never())
            ->method('count');

        $subscriber = new #[Subscriber('catch_all', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(Message $message): void
            {
            }
        };

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription('catch_all')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2], $indexes);
    }

    public function testLastIndex(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new ArrayStream([
                5 => Message::create(new ProfileVisited(ProfileId::fromString('5'))),
            ]));

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
        );

        self::assertSame(5, $loader->lastIndex());
    }

    public function testLastIndexOnEmptyStore(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new ArrayStream());

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
        );

        self::assertSame(0, $loader->lastIndex());
    }

    public function testStreamWithoutIndex(): void
    {
        $stream = $this->createMockForIntersectionOfInterfaces([Stream::class, IteratorAggregate::class]);
        $stream
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([Message::create(new ProfileVisited(ProfileId::fromString('1')))]));
        $stream
            ->expects($this->once())
            ->method('index')
            ->willReturn(null);

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(0)))
            ->willReturn($stream);

        $subscriber = new #[Subscriber('catch_all', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(Message $message): void
            {
            }
        };

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
        );

        $this->expectException(UnexpectedError::class);

        iterator_to_array($loader->load(0, [new Subscription('catch_all')]));
    }

    public function testSubscriberWithoutMetadataLoadsAllEvents(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(0)))
            ->willReturn(new ArrayStream([
                1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
            ]));
        $store
            ->expects($this->never())
            ->method('count');

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->once())
            ->method('get')
            ->with('custom')
            ->willReturn($this->createMock(SubscriberAccessor::class));

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            $subscriberRepository,
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription('custom')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1], $indexes);
    }

    public function testEventNamesAndAliasesOfAllSubscriptionsAreLoaded(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited', 'old_profile_visited']), new ToIndexCriterion(2)))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(2)))
            ->willReturn(1);

        $eventMetadataFactory = $this->createMock(EventMetadataFactory::class);
        $eventMetadataFactory
            ->expects($this->exactly(2))
            ->method('metadata')
            ->with(ProfileVisited::class)
            ->willReturn(new EventMetadata('profile_visited', aliases: ['old_profile_visited']));

        $subscriber = new #[Subscriber('other', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
            }
        };

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            $eventMetadataFactory,
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber(), $subscriber]),
            new FrozenClock($recordedOn),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID), new Subscription('other')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1], $indexes);
    }

    public function testGapAtEdgeOfDetectionWindowIsNotChecked(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))
            ->willReturn(2);

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn->add(new DateInterval('PT5M'))),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 3], $indexes);
    }

    public function testGapAtEdgeOfDetectionWindowForAggregateHeaderIsNotChecked(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new AggregateHeader('profile', '3', 1, $recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new AggregateHeader('profile', '1', 1, $recordedOn)),
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new AggregateHeader('profile', '3', 1, $recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->once())
            ->method('count')
            ->with(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))
            ->willReturn(2);

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn->add(new DateInterval('PT5M'))),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 3], $indexes);
    }

    public function testGapWithoutRecordedOnIsChecked(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4'))),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1'))),
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4'))),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(5)))->evaluate($criteria, '', true) => 3,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => 0,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testGapIsAlwaysCheckedWithoutDetectionWindow(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => new ArrayStream([
                    1 => Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                    4 => Message::create(new ProfileVisited(ProfileId::fromString('4')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(3))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(5)))->evaluate($criteria, '', true) => 3,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(4), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => 0,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn->add(new DateInterval('P1Y'))),
            detectionWindow: null,
        );

        $stream = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testDefaultRetries(): void
    {
        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $this->createMock(Store::class),
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([]),
        );

        $property = new ReflectionProperty(EventFilteredGapResolverStoreMessageLoader::class, 'retriesInMs');

        self::assertSame([0, 5, 50, 500], $property->getValue($loader));
    }

    public function testStreamIsClosedBeforeRetry(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $firstStream = $this->createMockForIntersectionOfInterfaces([Stream::class, IteratorAggregate::class]);
        $firstStream
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([
                Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
            ]));
        $firstStream
            ->expects($this->once())
            ->method('index')
            ->willReturn(2);
        $firstStream
            ->expects($this->once())
            ->method('close');

        $secondStream = $this->createMockForIntersectionOfInterfaces([Stream::class, IteratorAggregate::class]);
        $secondStream
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([
                Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                Message::create(new ProfileVisited(ProfileId::fromString('2')))->withHeader(new RecordedOnHeader($recordedOn)),
            ]));
        $secondStream
            ->expects($this->exactly(2))
            ->method('index')
            ->willReturnOnConsecutiveCalls(1, 2);
        $secondStream
            ->expects($this->once())
            ->method('close');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(0)))
            ->willReturnOnConsecutiveCalls($firstStream, $secondStream);

        $subscriber = new #[Subscriber('catch_all', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(Message $message): void
            {
            }
        };

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new FrozenClock($recordedOn),
            [0],
        );

        $stream = $loader->load(0, [new Subscription('catch_all')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2], $indexes);
    }

    public function testStreamIsClosedWhenGapIsAccepted(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $stream = $this->createMockForIntersectionOfInterfaces([Stream::class, IteratorAggregate::class]);
        $stream
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([
                Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new RecordedOnHeader($recordedOn)),
                Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
            ]));
        $stream
            ->expects($this->exactly(2))
            ->method('index')
            ->willReturnOnConsecutiveCalls(1, 3);
        $stream
            ->expects($this->once())
            ->method('close');

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(3))
            ->method('load')
            ->willReturnCallback(static fn (Criteria|null $criteria = null, int|null $limit = null, int|null $offset = null, bool $backwards = false) => match (true) {
                $criteria === null && $limit === 1 && $backwards => new ArrayStream([
                    3 => Message::create(new ProfileVisited(ProfileId::fromString('3')))->withHeader(new RecordedOnHeader($recordedOn)),
                ]),
                self::equalTo(new Criteria(new FromIndexCriterion(0), new EventsCriterion(['profile_visited'])))->evaluate($criteria, '', true) => $stream,
                default => throw new RuntimeException('Unexpected load'),
            });
        $store
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnCallback(static fn (Criteria $criteria) => match (true) {
                self::equalTo(new Criteria(new FromIndexCriterion(0), new ToIndexCriterion(4)))->evaluate($criteria, '', true) => 2,
                self::equalTo(new Criteria(new FromIndexCriterion(1), new ToIndexCriterion(3)))->evaluate($criteria, '', true) => 0,
                default => throw new RuntimeException('Unexpected count'),
            });

        $loader = new EventFilteredGapResolverStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
            new FrozenClock($recordedOn),
            [],
        );

        $result = $loader->load(0, [new Subscription(BatchingSubscriber::ID)]);

        $indexes = [];

        foreach ($result as $message) {
            $indexes[] = $result->index();
        }

        self::assertSame([1, 3], $indexes);
    }
}
