<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use ArrayIterator;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use EmptyIterator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\LockCouldNotBeAcquired;
use Patchlevel\EventSourcing\Store\LockCouldNotBeFreed;
use Patchlevel\EventSourcing\Store\MissingDataForStorage;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Store\WrongQueryResult;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileEmailChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PDO;
use Pdo\Pgsql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

use function iterator_to_array;
use function method_exists;

#[CoversClass(StreamDoctrineDbalStore::class)]
final class StreamDoctrineDbalStoreTest extends TestCase
{
    public function testLoadWithNoEvents(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithLimit(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC LIMIT 10', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
            10,
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithOffset(): void
    {
        if (method_exists(AbstractPlatform::class, 'supportsLimitOffset')) {
            $this->markTestSkipped('In older DBAL versions platforms did not need to support this');
        }

        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC OFFSET 5', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
            offset: 5,
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithIndex(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (id > :from_index) AND (archived = :archived) ORDER BY id ASC', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
            'from_index' => 1,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->fromIndex(1)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithLike(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream LIKE :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
            'stream_0' => 'profile-%',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-*')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithLikeAll(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('*')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadMultipleStream(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new EmptyIterator());

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE ((stream LIKE :stream_0) OR (stream = :stream_1)) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
            'stream_0' => 'profile-%',
            'stream_1' => 'foo',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName(['profile-*', 'foo'])
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithOneEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result->method('iterateAssociative')->willReturn(new ArrayIterator(
            [
                [
                    'id' => 1,
                    'stream' => 'profile-1',
                    'playhead' => '1',
                    'event_id' => '1',
                    'event_name' => 'profile.created',
                    'event_payload' => '{"profileId": "1", "email": "s"}',
                    'recorded_on' => '2021-02-17 10:00:00',
                    'archived' => '0',
                    'custom_headers' => '[]',
                ],
            ],
        ));

        $connection->method('executeQuery')->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);

        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $abstractPlatform->expects($this->once())->method('getDateTimeTzFormatString')->willReturn('Y-m-d H:i:s');

        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->method('deserialize')->with(new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'))->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('deserialize')->with('[]')->willReturn([]);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());

        $message = $stream->current();

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());

        self::assertInstanceOf(Message::class, $message);
        self::assertInstanceOf(ProfileCreated::class, $message->event());
        self::assertSame('profile-1', $message->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $message->header(PlayheadHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 10:00:00'),
            $message->header(RecordedOnHeader::class)->recordedOn,
        );

        iterator_to_array($stream);

        self::assertSame(null, $stream->index());
        self::assertSame(0, $stream->position());
    }

    public function testLoadWithTwoEvents(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new ArrayIterator(
                [
                    [
                        'id' => 1,
                        'stream' => 'profile-1',
                        'playhead' => '1',
                        'event_id' => '1',
                        'event_name' => 'profile.created',
                        'event_payload' => '{"profileId": "1", "email": "s"}',
                        'recorded_on' => '2021-02-17 10:00:00',
                        'archived' => '0',
                        'custom_headers' => '[]',
                    ],
                    [
                        'id' => 2,
                        'stream' => 'profile-1',
                        'playhead' => '2',
                        'event_id' => '2',
                        'event_name' => 'profile.email_changed',
                        'event_payload' => '{"profileId": "1", "email": "d"}',
                        'recorded_on' => '2021-02-17 11:00:00',
                        'archived' => '0',
                        'custom_headers' => '[]',
                    ],
                ],
            ));

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC',
                [
                    'stream_0' => 'profile-1',
                    'from_playhead' => 0,
                    'archived' => false,
                ],
                $this->isArray(),
            )
            ->willReturn($result);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform
            ->expects($this->once())
            ->method('createSelectSQLBuilder')
            ->willReturn(new DefaultSelectSQLBuilder(
                $abstractPlatform,
                'FOR UPDATE',
                'SKIP LOCKED',
            ));
        $abstractPlatform
            ->expects($this->exactly(2))
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s');

        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $queryBuilder = new QueryBuilder($connection);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $connection
            ->expects($this->once())
            ->method('createExpressionBuilder')
            ->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnCallback(new ReturnCallback([
                [
                    [new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'), []],
                    new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')),
                ],
                [
                    [new SerializedEvent('profile.email_changed', '{"profileId": "1", "email": "d"}'), []],
                    new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('d')),
                ],
            ]));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('deserialize')->with('[]')->willReturn([]);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());

        $message = $stream->current();

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());

        self::assertInstanceOf(Message::class, $message);
        self::assertInstanceOf(ProfileCreated::class, $message->event());
        self::assertSame('profile-1', $message->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $message->header(PlayheadHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 10:00:00'),
            $message->header(RecordedOnHeader::class)->recordedOn,
        );

        $stream->next();
        $message = $stream->current();

        self::assertSame(2, $stream->index());
        self::assertSame(1, $stream->position());

        self::assertInstanceOf(Message::class, $message);
        self::assertInstanceOf(ProfileEmailChanged::class, $message->event());
        self::assertSame('profile-1', $message->header(StreamNameHeader::class)->streamName);
        self::assertSame(2, $message->header(PlayheadHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 11:00:00'),
            $message->header(RecordedOnHeader::class)->recordedOn,
        );
    }

    public function testTransactional(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithMySQL(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", -1)', 1],
                ['SELECT RELEASE_LOCK("133742")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithMariaDB(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MariaDBPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", 2147482647)', 1],
                ['SELECT RELEASE_LOCK("133742")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithPostgreSQL(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('SELECT pg_advisory_xact_lock(133742)');

        $connection
            ->expects($this->atLeastOnce())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalNested(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('SELECT pg_advisory_xact_lock(133742)');

        $connection
            ->expects($this->exactly(2))
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional(static function () use ($store, $callback): void {
            $store->transactional($callback(...));
        });

        self::assertTrue($callback->called);
    }

    public function testTransactionalTwice(): void
    {
        $callback = new class () {
            public int $called = 0;

            public function __invoke(): void
            {
                $this->called++;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(4))
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->with('SELECT pg_advisory_xact_lock(133742)');

        $connection
            ->expects($this->exactly(2))
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $store->transactional($callback(...));
        $store->transactional($callback(...));

        self::assertEquals(2, $callback->called);
    }

    public function testTransactionalUnlockByException(): void
    {
        $callback = new class () {
            public function __invoke(): void
            {
                throw new RuntimeException();
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MariaDBPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", 2147482647)', 1],
                ['SELECT RELEASE_LOCK("133742")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(RuntimeException::class);

        $store->transactional($callback(...));
    }

    public function testTransactionalWithMariaDBCustomLockTimeout(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MariaDBPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", 5)', 1],
                ['SELECT RELEASE_LOCK("133742")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
            config: ['lock_timeout' => 5],
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithMariaDBZeroLockTimeout(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MariaDBPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", 0)', 1],
                ['SELECT RELEASE_LOCK("133742")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
            config: ['lock_timeout' => 0],
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalLockCouldNotBeAcquiredByTimeout(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT GET_LOCK("133742", 5)')
            ->willReturn(0);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
            config: ['lock_timeout' => 5],
        );

        $this->expectException(LockCouldNotBeAcquired::class);
        $this->expectExceptionMessage('The lock with id [133742] could not be acquired with a timeout of 5');

        $store->transactional($callback(...));
    }

    public function testTransactionalLockCouldNotBeAcquiredByError(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT GET_LOCK("133742", -1)')
            ->willReturn(null);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(LockCouldNotBeAcquired::class);
        $this->expectExceptionMessage('There was an error when tried to get the lock with id [133742]');

        $store->transactional($callback(...));
    }

    public function testTransactionalLockCouldNotBeFreedNotOurs(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", -1)', 1],
                ['SELECT RELEASE_LOCK("133742")', 0],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(LockCouldNotBeFreed::class);
        $this->expectExceptionMessage('The lock with id [133742] could not be freed as it is not ours');

        $store->transactional($callback(...));
    }

    public function testTransactionalLockCouldNotBeFreedNotExist(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("133742", -1)', 1],
                ['SELECT RELEASE_LOCK("133742")', null],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(LockCouldNotBeFreed::class);
        $this->expectExceptionMessage('The lock with id [133742] could not be freed as it does not exist');

        $store->transactional($callback(...));
    }

    public function testSaveWithOneEvent(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('serialize')->with($message->event())->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with([])->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $mockedConnection->method('transactional')->willReturnCallback(
            static fn (Closure $closure): mixed => $closure(),
        );

        $mockedConnection->expects($this->once())->method('executeStatement')->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', $recordedOn, false, '[]'], [
            5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
            6 => Type::getType(Types::BOOLEAN),
        ]);

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );
        $singleTableStore->save($message);
    }

    public function testSaveWithoutStreamNameHeader(): void
    {
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('serialize')->with($message->event())->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with([])->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $mockedConnection->method('transactional')->willReturnCallback(
            static fn (Closure $closure): mixed => $closure(),
        );

        $mockedConnection->expects($this->never())->method('executeStatement');

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(MissingDataForStorage::class);
        $singleTableStore->save($message);
    }

    public function testSaveWithTwoEvents(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new EventIdHeader('1'));
        $message2 = Message::create(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('d')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(2))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new EventIdHeader('2'));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturnMap([
                [$message1->event(), new SerializedEvent('profile_created', '')],
                [$message2->event(), new SerializedEvent('profile_email_changed', '')],
            ]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    'profile-1',
                    1,
                    '1',
                    'profile_created',
                    '',
                    $recordedOn,
                    false,
                    '[]',
                    'profile-1',
                    2,
                    '2',
                    'profile_email_changed',
                    '',
                    $recordedOn,
                    false,
                    '[]',
                ],
                [
                    5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    6 => Type::getType(Types::BOOLEAN),
                    13 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    14 => Type::getType(Types::BOOLEAN),
                ],
            );

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );
        $singleTableStore->save($message1, $message2);
    }

    public function testSaveWithUniqueConstraintViolation(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new RecordedOnHeader($recordedOn));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('2'))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->exactly(2))->method('serialize')->with($message1->event())->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with([])->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $mockedConnection->method('transactional')->willReturnCallback(
            static fn (Closure $closure): mixed => $closure(),
        );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    'profile-1',
                    1,
                    '1',
                    'profile_created',
                    '',
                    $recordedOn,
                    false,
                    '[]',
                    'profile-1',
                    1,
                    '2',
                    'profile_created',
                    '',
                    $recordedOn,
                    false,
                    '[]',
                ],
                [
                    5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    6 => Type::getType(Types::BOOLEAN),
                    13 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    14 => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willThrowException(new UniqueConstraintViolationException(new Exception('foo'), null));

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(UniqueConstraintViolation::class);
        $singleTableStore->save($message1, $message2);
    }

    public function testSaveWithThousandEvents(): void
    {
        $recordedOn = new DateTimeImmutable();

        $messages = [];
        for ($i = 1; $i <= 10000; $i++) {
            $messages[] = Message::create(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('s')))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader($i))
                ->withHeader(new RecordedOnHeader($recordedOn));
        }

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->exactly(10000))->method('serialize')->with($messages[0]->event())->willReturn(new SerializedEvent(
            'profile_email_changed',
            '',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with([])->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $mockedConnection->method('transactional')->willReturnCallback(
            static fn (Closure $closure): mixed => $closure(),
        );

        $mockedConnection->expects($this->exactly(2))->method('executeStatement');

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );
        $singleTableStore->save(...$messages);
    }

    public function testSaveWithCustomHeaders(): void
    {
        $customHeaders = [
            new FooHeader('foo'),
            new BazHeader('baz'),
        ];

        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new EventIdHeader('1'))
            ->withHeaders($customHeaders);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->once())->method('serialize')->with($message->event())->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with($customHeaders)->willReturn('{foo: "foo", baz: "baz"}');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $mockedConnection->method('transactional')->willReturnCallback(
            static fn (Closure $closure): mixed => $closure(),
        );

        $mockedConnection->expects($this->once())->method('executeStatement')->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', $recordedOn, false, '{foo: "foo", baz: "baz"}'], [
            5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
            6 => Type::getType(Types::BOOLEAN),
        ]);

        $singleTableStore = new StreamDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $headersSerializer,
        );
        $singleTableStore->save($message);
    }

    public function testCount(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->with('SELECT COUNT(*) FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived)', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn('1');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $count = $doctrineDbalStore->count(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(1, $count);
    }

    public function testCountWrongResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->with('SELECT COUNT(*) FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived)', [
            'stream_0' => 'profile-1',
            'from_playhead' => 0,
            'archived' => false,
        ], $this->isArray())->willReturn([]);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects($this->once())->method('createSelectSQLBuilder')->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform,
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $queryBuilder = new QueryBuilder($connection);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $this->expectException(WrongQueryResult::class);
        $doctrineDbalStore->count(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );
    }

    public function testSetupSubscription(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnMap([
                [
                    <<<'SQL'
                CREATE OR REPLACE FUNCTION notify_event_store() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('event_store', NEW.stream::text);
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
                    1,
                ],
                ['DROP TRIGGER IF EXISTS notify_trigger ON event_store;', 1],
                ['CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON event_store FOR EACH ROW EXECUTE PROCEDURE notify_event_store();', 1],
            ]);

        $abstractPlatform = $this->createMock(PostgreSQLPlatform::class);
        $connection->expects($this->once())->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testSetupSubscriptionWithOtherStoreTableName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnMap([
                [
                    <<<'SQL'
                CREATE OR REPLACE FUNCTION new.notify_event_store() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('new.event_store', NEW.stream::text);
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
                    1,
                ],
                ['DROP TRIGGER IF EXISTS notify_trigger ON new.event_store;', 1],
                ['CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON new.event_store FOR EACH ROW EXECUTE PROCEDURE new.notify_event_store();', 1],
            ]);

        $abstractPlatform = $this->createMock(PostgreSQLPlatform::class);
        $connection->expects($this->once())->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $clock = $this->createMock(ClockInterface::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
            $clock,
            ['table_name' => 'new.event_store'],
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testSetupSubscriptionNotPostgres(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $connection->expects($this->once())->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );
        $doctrineDbalStore->setupSubscription();
    }

    #[RequiresPhp('>= 8.4')]
    public function testWait(): void
    {
        $nativeConnection = $this->createMock(Pgsql::class);
        $nativeConnection
            ->expects($this->once())
            ->method('getNotify')
            ->with(PDO::FETCH_ASSOC, 100)
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('LISTEN "event_store"')
            ->willReturn(1);
        $connection
            ->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn($nativeConnection);

        $abstractPlatform = $this->createMock(PostgreSQLPlatform::class);
        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );
        $doctrineDbalStore->wait(100);
    }

    #[RequiresPhp('< 8.4')]
    public function testWaitDeprecatedFunction(): void
    {
        $nativeConnection = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->addMethods(['pgsqlGetNotify'])
            ->getMock();
        $nativeConnection
            ->expects($this->once())
            ->method('pgsqlGetNotify')
            ->with(PDO::FETCH_ASSOC, 100)
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('LISTEN "event_store"')
            ->willReturn(1);
        $connection
            ->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn($nativeConnection);

        $abstractPlatform = $this->createMock(PostgreSQLPlatform::class);
        $connection->method('getDatabasePlatform')->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );
        $doctrineDbalStore->wait(100);
    }

    public function testConfigureSchemaWithDifferentDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('getParams')->willReturn(['dbname' => 'db']);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $differentConnection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('getParams')->willReturn(['dbname' => 'db2']);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $differentConnection);

        self::assertEquals(new Schema(), $schema);
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventSerializer = $this->createMock(EventSerializer::class);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new StreamDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $headersSerializer,
        );

        $expectedSchema = new Schema();
        $table = $expectedSchema->createTable('event_store');
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('stream', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(false);
        $table->addColumn('event_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('event_name', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('event_payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['event_id']);
        $table->addUniqueIndex(['stream', 'playhead']);
        $table->addIndex(['stream', 'playhead', 'archived']);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $connection);

        self::assertEquals($expectedSchema, $schema);
    }
}
