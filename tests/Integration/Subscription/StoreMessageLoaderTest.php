<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\GapDetection;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriberEventFilter;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
final class StoreMessageLoaderTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testLoadOnlySubscribedEventsWithoutHoles(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jim'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(3))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testSubscribedEventWrittenIntoFilteredRangeIsNotSkipped(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();

            if ($stream->index() !== 1) {
                continue;
            }

            // simulates a transaction that commits after the stream was loaded
            $store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'Late'))
                    ->withHeader(new StreamNameHeader('profile-3'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader($recordedOn))
                    ->withHeader(new IndexHeader(3)),
            );
        }

        self::assertSame([1, 3, 4], $indexes);
    }

    public function testPermanentGapIsAcceptedWithoutDuplicates(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Anna'))
                ->withHeader(new StreamNameHeader('profile-5'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(5)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4, 5], $indexes);
    }

    public function testRolledBackSubscribedEventIsSkipped(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $johnId = ProfileId::generate();
        $janeId = ProfileId::generate();
        $tomId = ProfileId::generate();

        $store->save(
            Message::create(new ProfileCreated($johnId, 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        try {
            $store->transactional(static function () use ($store, $recordedOn): void {
                $store->save(
                    Message::create(new ProfileCreated(ProfileId::generate(), 'Rolled back'))
                        ->withHeader(new StreamNameHeader('profile-2'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader($recordedOn)),
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // the index of the rolled back event may be consumed, depending on the database
        }

        $store->save(
            Message::create(new NameChanged($janeId, 'Jane'))
                ->withHeader(new StreamNameHeader('profile-3'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new ProfileCreated($tomId, 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $events = [];

        foreach ($stream as $message) {
            $events[] = $message->event();
        }

        self::assertEquals(
            [
                new ProfileCreated($johnId, 'John'),
                new ProfileCreated($tomId, 'Tom'),
            ],
            $events,
        );
    }

    public function testRolledBackEventIsSkippedWithoutFilter(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $johnId = ProfileId::generate();
        $janeId = ProfileId::generate();
        $tomId = ProfileId::generate();

        $store->save(
            Message::create(new ProfileCreated($johnId, 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        try {
            $store->transactional(static function () use ($store, $recordedOn): void {
                $store->save(
                    Message::create(new ProfileCreated(ProfileId::generate(), 'Rolled back'))
                        ->withHeader(new StreamNameHeader('profile-2'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader($recordedOn)),
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // the index of the rolled back event may be consumed, depending on the database
        }

        $store->save(
            Message::create(new NameChanged($janeId, 'Jane'))
                ->withHeader(new StreamNameHeader('profile-3'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new ProfileCreated($tomId, 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader(
            $store,
            gapDetection: new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $events = [];

        foreach ($stream as $message) {
            $events[] = $message->event();
        }

        self::assertEquals(
            [
                new ProfileCreated($johnId, 'John'),
                new NameChanged($janeId, 'Jane'),
                new ProfileCreated($tomId, 'Tom'),
            ],
            $events,
        );
    }

    public function testEventWrittenIntoGapIsNotSkippedWithoutFilter(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new StoreMessageLoader(
            $store,
            gapDetection: new GapDetection(
                new FrozenClock($recordedOn),
                [0, 0],
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();

            if ($stream->index() !== 2) {
                continue;
            }

            // simulates a transaction that commits after the stream was loaded
            $store->save(
                Message::create(new NameChanged(ProfileId::generate(), 'Late'))
                    ->withHeader(new StreamNameHeader('profile-3'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader($recordedOn))
                    ->withHeader(new IndexHeader(3)),
            );
        }

        self::assertSame([1, 2, 3, 4], $indexes);
    }

    public function testLoadOnlySubscribedEventsWithoutGapDetection(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 4], $indexes);
    }

    public function testGapAtEndOfDetectionWindowIsChecked(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock(new DateTimeImmutable('2020-01-01 00:04:59')),
                [0, 0],
                new DateInterval('PT5M'),
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();

            if ($stream->index() !== 1) {
                continue;
            }

            // simulates a transaction that commits after the stream was loaded
            $store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'Late'))
                    ->withHeader(new StreamNameHeader('profile-3'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader($recordedOn))
                    ->withHeader(new IndexHeader(3)),
            );
        }

        self::assertSame([1, 3, 4], $indexes);
    }

    public function testGapOutsideDetectionWindowIsNotChecked(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('SQLite returns rows inserted while the result is iterated, so the late event is loaded anyway');
        }

        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter(
                new AttributeEventMetadataFactory(),
                new MetadataSubscriberAccessorRepository([new ProfileProjection($this->connection)]),
            ),
            new GapDetection(
                new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
                [0, 0],
                new DateInterval('PT5M'),
            ),
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();

            if ($stream->index() !== 1) {
                continue;
            }

            // written too late: the gap is outside the detection window and therefore not checked anymore
            $store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'Late'))
                    ->withHeader(new StreamNameHeader('profile-3'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader($recordedOn))
                    ->withHeader(new IndexHeader(3)),
            );
        }

        self::assertSame([1, 4], $indexes);
    }
}
