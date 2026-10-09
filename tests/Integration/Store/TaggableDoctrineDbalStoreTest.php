<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatformMiddleware;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\LockCouldNotBeAcquired;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ExternEvent;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Store\Header\TraceHeader;
use Patchlevel\EventSourcing\Tests\PhpunitHelper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;
use Throwable;

use function array_map;
use function iterator_to_array;
use function json_decode;
use function sprintf;

#[CoversNothing]
final class TaggableDoctrineDbalStoreTest extends TestCase
{
    use PhpunitHelper;

    private Connection $connection;
    private TaggableDoctrineDbalStore $store;

    private ClockInterface $clock;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));

        $this->store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
            config: ['lock_timeout' => 1],
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $this->store,
        );

        $schemaDirector->create();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSave(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testSaveWithIndex(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new IndexHeader(1)),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00')))
                ->withHeader(new IndexHeader(42)),
        ];

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
            config: ['keep_index' => true],
        );

        $store->save(...$messages);

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(3))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        );

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(3, $result);

        $result1 = $result[0];

        self::assertEquals(1, $result1['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(42, $result2['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );

        $result3 = $result[2];

        self::assertEquals(43, $result3['id']);
        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result3['stream']);
        self::assertEquals('3', $result3['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result3['recorded_on']);
        self::assertEquals('profile.created', $result3['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result3['event_payload'], true),
        );
    }

    public function testSaveWithOnlyStreamName(): void
    {
        $messages = [
            Message::create(new ExternEvent('test 1'))
                ->withHeader(new StreamNameHeader('extern')),
            Message::create(new ExternEvent('test 2'))
                ->withHeader(new StreamNameHeader('extern')),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals('extern', $result1['stream']);
        self::assertEquals(null, $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('extern', $result1['event_name']);
        self::assertEquals(
            ['message' => 'test 1'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals('extern', $result2['stream']);
        self::assertEquals(null, $result2['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result2['recorded_on']);
        self::assertEquals('extern', $result2['event_name']);
        self::assertEquals(
            ['message' => 'test 2'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testSaveWithTransactional(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->transactional(function () use ($messages): void {
            $this->store->save(...$messages);
        });

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );
    }

    public function testArchive(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new EventIdHeader('2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        ];

        $this->store->save(...$messages);
        $this->store->archive(
            new Criteria(
                new StreamCriterion(sprintf('profile-%s', $profileId->toString())),
                new ToPlayheadCriterion(2),
            ),
        );

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store ORDER BY id');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result1['stream']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result1['event_payload'], true),
        );

        self::assertEquals('1', $result1['archived']);

        $result2 = $result[1];

        self::assertEquals(sprintf('profile-%s', $profileId->toString()), $result2['stream']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event_name']);
        self::assertEquals(
            ['profileId' => $profileId->toString(), 'name' => 'test'],
            json_decode($result2['event_payload'], true),
        );

        self::assertEquals('0', $result2['archived']);
    }

    public function testUniqueStreamNameAndPlayheadConstraint(): void
    {
        $this->expectException(UniqueConstraintViolation::class);

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        ];

        $this->store->save(...$messages);
    }

    public function testUniqueEventIdConstraint(): void
    {
        $this->expectException(UniqueConstraintViolation::class);

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new EventIdHeader('1'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        ];

        $this->store->save(...$messages);
    }

    public function testSave10000Messages(): void
    {
        $profileId = ProfileId::generate();

        $messages = [];

        for ($i = 1; $i <= 10000; $i++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader($i))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));
        }

        $this->store->save(...$messages);

        /** @var int $result */
        $result = $this->connection->fetchFirstColumn('SELECT COUNT(*) FROM event_store')[0];

        self::assertEquals(10000, $result);
    }

    public function testSaveLockTimeout(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->markTestSkipped('SQLite does not support locks');
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestSkipped('PostgreSQL does lock indefinitely');
        }

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        ];

        $connection = DriverManager::getConnection($this->connection->getParams());

        $lock = $connection->fetchOne(
            sprintf(
                'SELECT GET_LOCK("%s", %d)',
                133742,
                1,
            ),
        );
        self::assertSame(1, $lock);

        $this->expectException(LockCouldNotBeAcquired::class);
        $this->expectExceptionMessage('The lock with id [133742] could not be acquired with a timeout of 1');
        try {
            $this->store->save(...$messages);
        } finally {
            $connection->close();
        }
    }

    public function testLoad(): void
    {
        $profileId = ProfileId::generate();

        $message = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));

        $this->store->save($message);

        $stream = null;

        try {
            $stream = $this->store->load();

            self::assertSame(1, $stream->index());
            self::assertSame(0, $stream->position());

            $loadedMessage = $stream->current();

            self::assertInstanceOf(Message::class, $loadedMessage);
            self::assertNotSame($message, $loadedMessage);
            self::assertEquals($message->event(), $loadedMessage->event());
            self::assertEquals(
                $message->header(StreamNameHeader::class)->streamName,
                $loadedMessage->header(StreamNameHeader::class)->streamName,
            );
            self::assertEquals(
                $message->header(PlayheadHeader::class)->playhead,
                $loadedMessage->header(PlayheadHeader::class)->playhead,
            );
            self::assertEquals(
                $message->header(RecordedOnHeader::class)->recordedOn,
                $loadedMessage->header(RecordedOnHeader::class)->recordedOn,
            );
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithWildcard(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId1->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId2->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new StreamCriterion('profile-*')));

            $messages = iterator_to_array($stream);

            self::assertCount(2, $messages);
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new StreamCriterion('*-*')));

            $messages = iterator_to_array($stream);

            self::assertCount(2, $messages);
        } finally {
            $stream?->close();
        }
    }

    public function testTags(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(
                new Criteria(
                    new TagCriterion(['profile:' . $profileId1->toString()]),
                ),
            );

            $messages = $stream->toList();

            self::assertCount(2, $messages);

            self::assertEquals(
                ['profile:' . $profileId1->toString()],
                $messages[0]->header(TagsHeader::class)->tags,
            );

            self::assertEquals(
                ['profile:' . $profileId1->toString(), 'profile:' . $profileId2->toString()],
                $messages[1]->header(TagsHeader::class)->tags,
            );
        } finally {
            $stream?->close();
        }
    }

    public function testAppendWithoutAppendCondition(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->store->append($messages);
        self::assertStreamEquals($messages, $this->store->load());
    }

    public function testQueryTags(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()]),
        ));

        self::assertStreamEquals([$message1, $message3], $stream);
    }

    public function testQueryEvents(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(events: [ProfileCreated::class]),
        ));

        self::assertStreamEquals([$message1, $message2], $stream);
    }

    public function testQueryStream(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('baz'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(streamName: 'foo'),
            new SubQuery(streamName: 'baz'),
        ));

        self::assertStreamEquals([$message1, $message3], $stream);
    }

    public function testQueryTagAndEvent(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()], [ProfileCreated::class]),
        ));

        self::assertStreamEquals([$message1], $stream);
    }

    public function testOnlyLastEvent(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));

        $this->store->append([$message1, $message2]);

        $stream = $this->store->query(
            new Query(
                new SubQuery(
                    events: [ProfileCreated::class],
                    onlyLastEvent: true,
                ),
            ),
        );

        self::assertStreamEquals([$message2], $stream);
    }

    public function testQueryWithEmptySubQueryMatchesAll(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));

        $this->store->append([$message1, $message2]);

        $stream = $this->store->query(new Query(
            new SubQuery(),
            new SubQuery(['profile:' . $profileId1->toString()]),
        ));

        self::assertStreamEquals([$message1, $message2], $stream);
    }

    public function testQueryWithEmptyOnlyLastEventSubQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(new Query(
            new SubQuery(onlyLastEvent: true),
            new SubQuery(['profile:' . $profileId1->toString()]),
        ));

        self::assertStreamEquals([$message1, $message3], $stream);
    }

    public function testQueryFrom(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));

        $this->store->append([$message1, $message2, $message3]);

        $stream = $this->store->query(
            new Query(new SubQuery(['profile:' . $profileId1->toString()])),
            2,
        );

        self::assertStreamEquals([$message3], $stream);
    }

    public function testQueryFromIsInclusive(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));

        $this->store->append([$message1, $message2]);

        self::assertStreamEquals([$message2], $this->store->query(new Query(), 2));
    }

    public function testQueryFromWithOnlyLastEventBeforeFrom(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));

        $this->store->append([$message1, $message2]);

        $stream = $this->store->query(
            new Query(new SubQuery(['profile:' . $profileId1->toString()], onlyLastEvent: true)),
            2,
        );

        self::assertStreamEquals([], $stream);
    }

    public function testComplexQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId1, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));
        $message2 = Message::create(new ProfileCreated($profileId2, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()]));
        $message3 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('main'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));
        $message4 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([]));

        $this->store->append([$message1, $message2, $message3, $message4]);

        $stream = $this->store->query(new Query(
            new SubQuery(['profile:' . $profileId1->toString()], [ProfileCreated::class]),
            new SubQuery(['profile:' . $profileId2->toString()]),
            new SubQuery(events: [ExternEvent::class]),
            new SubQuery(streamName: 'foo'),
        ));

        self::assertStreamEquals([$message1, $message2, $message3, $message4], $stream);
    }

    public function testAppendRaceCondition(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $messages = [
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            $messages,
            new AppendCondition(
                new Query(new SubQuery(['profile:' . $profileId1->toString()])),
                0,
            ),
        );
    }

    public function testAppendConditionWithAfterHigherThanLastMatchingEvent(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $message = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()]));

        $this->store->append(
            [$message],
            new AppendCondition(
                new Query(new SubQuery(['profile:' . $profileId1->toString()])),
                2,
            ),
        );

        self::assertStreamEquals([...$messages, $message], $this->store->load());
    }

    public function testAppendConditionWithMatchingEventAfter(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId->toString()])),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader(['profile:' . $profileId->toString()])),
        ];

        $this->store->append($messages);

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            [
                Message::create(new ExternEvent('test message'))
                    ->withHeader(new StreamNameHeader('foo'))
                    ->withHeader(new TagsHeader(['profile:' . $profileId->toString()])),
            ],
            new AppendCondition(
                new Query(new SubQuery(['profile:' . $profileId->toString()])),
                1,
            ),
        );
    }

    public function testAppendConditionRejectsEntireBatch(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $this->store->append([
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
        ]);

        $batch = [
            Message::create(new ExternEvent('first'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
            Message::create(new ExternEvent('second'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        try {
            $this->store->append(
                $batch,
                new AppendCondition(
                    new Query(new SubQuery(['profile:' . $profileId1->toString()])),
                    0,
                ),
            );

            self::fail('Expected AppendConditionNotMet to be thrown');
        } catch (AppendConditionNotMet) {
        }

        // not even the first event of the rejected batch may have landed
        self::assertCount(1, iterator_to_array($this->store->load()));
    }

    public function testAppendConditionAppliesEntireBatch(): void
    {
        $profileId = ProfileId::generate();

        $first = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId->toString()]));

        $second = Message::create(new ExternEvent('second'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId->toString()]));

        $this->store->append(
            [$first, $second],
            new AppendCondition(
                new Query(new SubQuery(['profile:' . $profileId->toString()])),
                0,
            ),
        );

        self::assertStreamEquals([$first, $second], $this->store->load());
    }

    public function testAppendRaceConditionEmptyQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $messages = [
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new TagsHeader([
                    'profile:' . $profileId1->toString(),
                    'profile:' . $profileId2->toString(),
                ])),
        ];

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            $messages,
            new AppendCondition(
                new Query(),
                0,
            ),
        );
    }

    public function testAppendWithEmptyQuery(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId1, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId1->toString()])),
            Message::create(new ProfileCreated($profileId2, 'test'))
                ->withHeader(new StreamNameHeader('foo'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new TagsHeader(['profile:' . $profileId2->toString()])),
        ];

        $this->store->append($messages);

        $message = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([
                'profile:' . $profileId1->toString(),
                'profile:' . $profileId2->toString(),
            ]));

        $this->store->append(
            [$message],
            new AppendCondition(
                new Query(),
                2,
            ),
        );

        self::assertStreamEquals([...$messages, $message], $this->store->load());
    }

    public function testAppendWithoutMessages(): void
    {
        $profileId = ProfileId::generate();

        $message = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId->toString()]));

        $this->store->append([$message]);
        $this->store->append([]);
        $this->store->append([], new AppendCondition(new Query(), 0));

        self::assertStreamEquals([$message], $this->store->load());
    }

    public function testAppendConditionWithEmptySubQuery(): void
    {
        $profileId = ProfileId::generate();

        $message1 = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
            ->withHeader(new TagsHeader(['profile:' . $profileId->toString()]));

        $this->store->append([$message1]);

        $message2 = Message::create(new ExternEvent('test message'))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new TagsHeader([]));

        $this->expectException(AppendConditionNotMet::class);

        $this->store->append(
            [$message2],
            new AppendCondition(
                new Query(
                    new SubQuery(),
                    new SubQuery(['unknown']),
                ),
                0,
            ),
        );
    }

    public function testStreams(): void
    {
        $profileId = ProfileId::fromString('0190e47e-77e9-7b90-bf62-08bbf0ab9b4b');

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $streams = $this->store->streams();

        self::assertEquals([
            'foo',
            'profile-0190e47e-77e9-7b90-bf62-08bbf0ab9b4b',
        ], $streams);
    }

    public function testRemove(): void
    {
        $profileId = ProfileId::fromString('0190e47e-77e9-7b90-bf62-08bbf0ab9b4b');

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        ];

        $this->store->save(...$messages);

        $streams = $this->store->streams();

        self::assertEquals([
            'foo',
            'profile-0190e47e-77e9-7b90-bf62-08bbf0ab9b4b',
        ], $streams);

        $this->store->remove(new Criteria(new StreamCriterion('profile-*')));

        $streams = $this->store->streams();

        self::assertEquals(['foo'], $streams);
    }

    public function testCount(): void
    {
        $profileId = ProfileId::generate();

        $this->store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1)),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(2)),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        );

        self::assertSame(3, $this->store->count());
        self::assertSame(2, $this->store->count(new Criteria(new StreamCriterion('profile-*'))));
        self::assertSame(1, $this->store->count(new Criteria(new StreamCriterion('foo'))));
    }

    public function testLoadEmptyStore(): void
    {
        $stream = null;

        try {
            $stream = $this->store->load();

            self::assertSame([], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        self::assertSame(0, $this->store->count());
    }

    public function testLoadMultipleStreams(): void
    {
        $this->store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                ->withHeader(new StreamNameHeader('profile-a'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                ->withHeader(new StreamNameHeader('profile-b'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                ->withHeader(new StreamNameHeader('profile-c'))
                ->withHeader(new PlayheadHeader(3))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
        );

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new StreamCriterion('profile-a', 'profile-b')));

            self::assertSame([1, 2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithArchivedCriterion(): void
    {
        $profileId = ProfileId::generate();
        $streamName = sprintf('profile-%s', $profileId->toString());

        $this->store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader($streamName))
                ->withHeader(new PlayheadHeader(1)),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader($streamName))
                ->withHeader(new PlayheadHeader(2)),
        );

        $this->store->archive(new Criteria(new StreamCriterion($streamName), new ToPlayheadCriterion(2)));

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new ArchivedCriterion(false)));

            self::assertSame([2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new ArchivedCriterion(true)));

            self::assertSame([1], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithEventCriteria(): void
    {
        $profileId = ProfileId::generate();
        $eventId = '0190e47e-77e9-7b90-bf62-08bbf0ab9b4b';

        $this->store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new EventIdHeader($eventId)),
            Message::create(new ExternEvent('test message'))
                ->withHeader(new StreamNameHeader('foo')),
        );

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new EventsCriterion(['profile.created'])));

            self::assertCount(1, iterator_to_array($stream));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(new Criteria(new EventIdCriterion($eventId)));
            $messages = iterator_to_array($stream, false);

            self::assertCount(1, $messages);
            self::assertSame($eventId, $messages[0]->header(EventIdHeader::class)->eventId);
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithIndexCriteria(): void
    {
        $profileId = ProfileId::generate();
        $streamName = sprintf('profile-%s', $profileId->toString());

        $messages = [];

        for ($playhead = 1; $playhead <= 5; $playhead++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'name-' . $playhead))
                ->withHeader(new StreamNameHeader($streamName))
                ->withHeader(new PlayheadHeader($playhead));
        }

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new FromIndexCriterion(3)));

            self::assertSame([4, 5], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new ToIndexCriterion(3)));

            self::assertSame([1, 2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new FromPlayheadCriterion(2)));

            self::assertSame([3, 4, 5], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(criteria: new Criteria(new FromPlayheadCriterion(2), new ToPlayheadCriterion(5)));

            self::assertSame([3, 4], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }
    }

    public function testLoadWithLimitOffsetAndBackwards(): void
    {
        $profileId = ProfileId::generate();
        $streamName = sprintf('profile-%s', $profileId->toString());

        $messages = [];

        for ($playhead = 1; $playhead <= 5; $playhead++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'name-' . $playhead))
                ->withHeader(new StreamNameHeader($streamName))
                ->withHeader(new PlayheadHeader($playhead));
        }

        $this->store->save(...$messages);

        $stream = null;

        try {
            $stream = $this->store->load(limit: 2);

            self::assertSame([1, 2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(limit: 2, offset: 2);

            self::assertSame([3, 4], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(limit: 3, offset: 0);

            self::assertSame([1, 2, 3], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        $stream = null;

        try {
            $stream = $this->store->load(backwards: true);

            self::assertSame([5, 4, 3, 2, 1], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }
    }

    public function testLockIsAcquiredAgainAfterTimeout(): void
    {
        $this->skipIfNoLockTimeout();

        $otherConnection = DriverManager::getConnection($this->connection->getParams());

        try {
            self::assertSame(1, $otherConnection->fetchOne('SELECT GET_LOCK("133742", 1)'));

            $exception = null;

            try {
                $this->store->save(
                    Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                        ->withHeader(new StreamNameHeader('profile-a'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
                );
            } catch (Throwable $e) {
                $exception = $e;
            }

            self::assertInstanceOf(LockCouldNotBeAcquired::class, $exception);

            // the lock is still held by the other connection, so the second save must also wait for it
            $this->expectException(LockCouldNotBeAcquired::class);

            $this->store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                    ->withHeader(new StreamNameHeader('profile-a'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );
        } finally {
            $otherConnection->close();
        }
    }

    public function testLockIsReleasedAfterException(): void
    {
        $this->skipIfNoLockTimeout();

        try {
            $this->store->transactional(static function (): void {
                throw new RuntimeException('error');
            });
        } catch (RuntimeException) {
            // expected
        }

        $otherConnection = DriverManager::getConnection($this->connection->getParams());

        try {
            $otherStore = new TaggableDoctrineDbalStore(
                $otherConnection,
                DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
                (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
                clock: $this->clock,
                config: ['lock_timeout' => 1],
            );

            $otherStore->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                    ->withHeader(new StreamNameHeader('profile-a'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );

            self::assertSame(1, $this->store->count());
        } finally {
            $otherConnection->close();
        }
    }

    public function testSaveHoldsLockUntilCommit(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->markTestSkipped('SQLite does not support locks');
        }

        $probeConnection = DriverManager::getConnection($this->connection->getParams());
        $lockQuery = $probeConnection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? "SELECT COUNT(*) FROM pg_locks WHERE locktype = 'advisory' AND objid = 133742"
            : 'SELECT IS_USED_LOCK("133742") IS NOT NULL';

        $connection = new class ($this->connection->getParams(), $this->connection->getDriver()) extends Connection {
            public Closure|null $beforeCommit = null;

            public function commit(): void
            {
                if ($this->beforeCommit) {
                    ($this->beforeCommit)();
                }

                parent::commit();
            }
        };

        // Checks from a second session whether the lock is still held right before the commit.
        $lockHeldOnCommit = null;
        $connection->beforeCommit = static function () use ($probeConnection, $lockQuery, &$lockHeldOnCommit): void {
            $lockHeldOnCommit = $probeConnection->fetchOne($lockQuery) > 0;
        };

        $store = new TaggableDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $profileId = ProfileId::generate();

        try {
            $store->save(
                Message::create(new ProfileCreated($profileId, 'test'))
                    ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );
        } finally {
            $connection->close();
            $probeConnection->close();
        }

        self::assertTrue($lockHeldOnCommit);
    }

    public function testSaveWithCustomHeaders(): void
    {
        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            DefaultHeadersSerializer::createFromPaths([__DIR__ . '/Header']),
            clock: $this->clock,
        );

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                ->withHeader(new StreamNameHeader('profile-a'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))->withHeader(new TraceHeader('trace-1')),
        );

        $stream = null;

        try {
            $stream = $store->load();
            $loaded = $stream->current();

            self::assertInstanceOf(Message::class, $loaded);
            self::assertEquals(new TraceHeader('trace-1'), $loaded->header(TraceHeader::class));
        } finally {
            $stream?->close();
        }
    }

    public function testSaveWithIndexExactBatchSize(): void
    {
        $profileId = ProfileId::generate();

        $messages = [];

        // 65535 max parameters / 10 columns = 6553 messages per batch
        for ($i = 1; $i <= 6553; $i++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader($i))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')))
                ->withHeader(new IndexHeader($i));
        }

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
            config: ['keep_index' => true],
        );

        $store->save(...$messages);

        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $store->save(
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new StreamNameHeader(sprintf('profile-%s', $profileId->toString())))
                ->withHeader(new PlayheadHeader(6554))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-02 00:00:00'))),
        );

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store WHERE playhead = 6554');

        self::assertCount(1, $result);
        self::assertEquals(6554, $result[0]['id']);
    }

    public function testTransactionalNested(): void
    {
        $this->store->transactional(function (): void {
            $this->store->transactional(function (): void {
                $this->store->save(
                    Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                        ->withHeader(new StreamNameHeader('profile-a'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
                );
            });

            $this->store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                    ->withHeader(new StreamNameHeader('profile-a'))
                    ->withHeader(new PlayheadHeader(2))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );
        });

        $stream = null;

        try {
            $stream = $this->store->load();

            self::assertSame([1, 2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }

        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalRollsBackOnException(): void
    {
        $exception = null;

        try {
            $this->store->transactional(function (): void {
                $this->store->save(
                    Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                        ->withHeader(new StreamNameHeader('profile-a'))
                        ->withHeader(new PlayheadHeader(1))
                        ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
                );

                throw new RuntimeException('error');
            });
        } catch (RuntimeException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertSame(0, $this->store->count());
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalTwice(): void
    {
        $this->store->transactional(function (): void {
            $this->store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                    ->withHeader(new StreamNameHeader('profile-a'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );
        });

        $this->store->transactional(function (): void {
            $this->store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'test'))
                    ->withHeader(new StreamNameHeader('profile-a'))
                    ->withHeader(new PlayheadHeader(2))
                    ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00'))),
            );
        });

        $stream = null;

        try {
            $stream = $this->store->load();

            self::assertSame([1, 2], array_map(
                static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
                iterator_to_array($stream, false),
            ));
        } finally {
            $stream?->close();
        }
    }

    public function testUnsupportedCriterion(): void
    {
        $this->expectException(UnsupportedCriterion::class);

        $this->store->count(new Criteria(new stdClass()));
    }

    public function testWaitWithoutNotification(): void
    {
        // without postgres it just sleeps, with postgres no notification arrives within the timeout
        $this->store->wait(10);
        $this->store->wait(10);

        self::assertSame(0, $this->store->count());
    }

    public function testConfigureSchemaSameDatabase(): void
    {
        $connection = DbalManager::createConnection();
        $otherConnection = DbalManager::createConnection();

        $store = new TaggableDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertTrue($schema->hasTable('event_store'));
    }

    public function testConfigureSchemaNotSameDatabase(): void
    {
        $connection = DbalManager::createConnection();
        $otherConnection = DbalManager::createConnection('other');

        $store = new TaggableDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            clock: $this->clock,
        );

        $schema = new Schema();

        $store->configureSchema($schema, $otherConnection);

        self::assertFalse($schema->hasTable('event_store'));
    }

    public function testGinIndexOnTags(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestSkipped('only postgres supports gin indexes');
        }

        $connection = DriverManager::getConnection(
            $this->connection->getParams(),
            (new Configuration())->setMiddlewares([new PostgreSQLPlatformMiddleware()]),
        );

        $store = new TaggableDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
        );

        $schemaDirector = new DoctrineSchemaDirector($connection, $store);

        try {
            $schemaDirector->update();

            $indexDefinition = $connection->fetchOne(
                "SELECT indexdef FROM pg_indexes WHERE indexname = 'event_store_tags_gin_idx'",
            );

            self::assertSame(
                'CREATE INDEX event_store_tags_gin_idx ON public.event_store USING gin (tags jsonb_path_ops)',
                $indexDefinition,
            );
            self::assertSame([], $schemaDirector->dryRunUpdate());
        } finally {
            $connection->close();
        }
    }

    private function skipIfNoLockTimeout(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->markTestSkipped('SQLite does not support locks');
        }

        if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
            return;
        }

        $this->markTestSkipped('PostgreSQL does lock indefinitely');
    }
}
