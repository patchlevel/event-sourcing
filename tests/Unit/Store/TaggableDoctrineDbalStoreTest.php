<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use ArrayIterator;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use EmptyIterator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Criteria\EventIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\LockCouldNotBeAcquired;
use Patchlevel\EventSourcing\Store\LockCouldNotBeFreed;
use Patchlevel\EventSourcing\Store\LockingNotImplemented;
use Patchlevel\EventSourcing\Store\MissingDataForStorage;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
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
use Ramsey\Uuid\Uuid;
use RuntimeException;
use stdClass;

use function is_string;
use function iterator_to_array;
use function method_exists;

#[CoversClass(TaggableDoctrineDbalStore::class)]
final class TaggableDoctrineDbalStoreTest extends TestCase
{
    public function testLoadWithNoEvents(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC LIMIT 10', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC OFFSET 5', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (id > :from_index) AND (archived = :archived) ORDER BY id ASC', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
                'from_index' => 1,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream LIKE :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
                'stream_0' => 'profile-%',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);
        $queryBuilder = new QueryBuilder($connection);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE ((stream LIKE :stream_0) OR (stream = :stream_1)) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
                'stream_0' => 'profile-%',
                'stream_1' => 'foo',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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

        $connection
            ->expects($this->exactly(3))
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
                        'tags' => '[]',
                        'recorded_on' => '2021-02-17 10:00:00',
                        'archived' => '0',
                        'custom_headers' => '[]',
                    ],
                ],
            ));

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived) ORDER BY id ASC', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
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
            ->expects($this->once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s');

        $connection
            ->expects($this->exactly(3))
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
            ->expects($this->once())
            ->method('deserialize')
            ->with(new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'))
            ->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('[]')
            ->willReturn([]);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
                        'tags' => '[]',
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
                        'tags' => '[]',
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
            ->expects($this->exactly(3))
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

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('deserialize')
            ->with('[]')
            ->willReturn([]);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
            ->expects($this->once())
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', [], $recordedOn, false, '[]'], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save($message);
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

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
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
                "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    'profile-1',
                    1,
                    '1',
                    'profile_created',
                    '',
                    [],
                    $recordedOn,
                    false,
                    '[]',
                    'profile-1',
                    2,
                    '2',
                    'profile_email_changed',
                    '',
                    [],
                    $recordedOn,
                    false,
                    '[]',
                ],
                [
                    5 => Type::getType(Types::JSON),
                    6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    7 => Type::getType(Types::BOOLEAN),
                    14 => Type::getType(Types::JSON),
                    15 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    16 => Type::getType(Types::BOOLEAN),
                ],
            );

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save($message1, $message2);
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
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->with($message1->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    'profile-1',
                    1,
                    '1',
                    'profile_created',
                    '',
                    [],
                    $recordedOn,
                    false,
                    '[]',
                    'profile-1',
                    1,
                    '2',
                    'profile_created',
                    '',
                    [],
                    $recordedOn,
                    false,
                    '[]',
                ],
                [
                    5 => Type::getType(Types::JSON),
                    6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    7 => Type::getType(Types::BOOLEAN),
                    14 => Type::getType(Types::JSON),
                    15 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    16 => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willThrowException(new UniqueConstraintViolationException(new Exception('foo'), null));

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(UniqueConstraintViolation::class);
        $store->save($message1, $message2);
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
        $eventSerializer
            ->expects($this->exactly(10000))
            ->method('serialize')
            ->with($messages[0]->event())
            ->willReturn(new SerializedEvent(
                'profile_email_changed',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(10000))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->exactly(2))
            ->method('executeStatement');

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save(...$messages);
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
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($customHeaders)
            ->willReturn('{foo: "foo", baz: "baz"}');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', [], $recordedOn, false, '{foo: "foo", baz: "baz"}'], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save($message);
    }

    public function testAppendWithOneMessage(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(1);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->append([$message]);
    }

    public function testAppendWithTwoMessages(): void
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
            ->withHeader(new EventIdHeader('2'))
            ->withHeader(new TagsHeader(['foo']));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturnMap([
                [$message1->event(), new SerializedEvent('profile_created', '')],
                [$message2->event(), new SerializedEvent('profile_email_changed', '')],
            ]);

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0 UNION ALL SELECT :stream1, :playhead1, :event_id1, :event_name1, :event_payload1, :tags1, :recorded_on1, :archived1, :custom_headers1',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                    'stream1' => 'profile-1',
                    'playhead1' => 2,
                    'event_id1' => '2',
                    'event_name1' => 'profile_email_changed',
                    'event_payload1' => '',
                    'tags1' => ['foo'],
                    'recorded_on1' => $recordedOn,
                    'archived1' => false,
                    'custom_headers1' => '[]',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                    'tags1' => Type::getType(Types::JSON),
                    'recorded_on1' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived1' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(2);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->append([$message1, $message2]);
    }

    public function testAppendWithPostgresCasts(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0::int, :event_id0, :event_name0, :event_payload0::jsonb, :tags0::jsonb, :recorded_on0::timestamptz, :archived0::boolean, :custom_headers0::jsonb',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(1);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['locking' => false],
        );
        $store->append([$message]);
    }

    public function testAppendWithHeaderFallbacks(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0',
                $this->callback(static function (array $parameters) use ($now): bool {
                    return $parameters['stream0'] === 'main'
                        && $parameters['playhead0'] === null
                        && is_string($parameters['event_id0'])
                        && Uuid::isValid($parameters['event_id0'])
                        && $parameters['event_name0'] === 'profile_created'
                        && $parameters['event_payload0'] === ''
                        && $parameters['tags0'] === []
                        && $parameters['recorded_on0'] === $now
                        && $parameters['archived0'] === false
                        && $parameters['custom_headers0'] === '[]';
                }),
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(1);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            $clock,
        );
        $store->append([$message]);
    }

    public function testAppendWithAppendCondition(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->exactly(4))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($mockedConnection),
            );
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0 WHERE (SELECT events.id FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE stream = :param1) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id DESC LIMIT 1) = :highestId',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                    'highestId' => 5,
                    'param1' => 'profile-1',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                    'param1' => ParameterType::STRING,
                ],
            )
            ->willReturn(1);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->append(
            [$message],
            new AppendCondition(new Query(new SubQuery(streamName: 'profile-1')), 5),
        );
    }

    public function testAppendWithAppendConditionZeroSequence(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
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
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($mockedConnection),
            );
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0 WHERE NOT EXISTS (SELECT events.id FROM event_store events ORDER BY events.id DESC LIMIT 1)',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(1);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->append(
            [$message],
            new AppendCondition(new Query(), 0),
        );
    }

    public function testAppendConditionNotMet(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
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
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($mockedConnection),
            );
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0 WHERE (SELECT events.id FROM event_store events ORDER BY events.id DESC LIMIT 1) = :highestId',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                    'highestId' => 5,
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willReturn(0);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(AppendConditionNotMet::class);
        $store->append(
            [$message],
            new AppendCondition(new Query(), 5),
        );
    }

    public function testAppendWithUniqueConstraintViolation(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) SELECT :stream0, :playhead0, :event_id0, :event_name0, :event_payload0, :tags0, :recorded_on0, :archived0, :custom_headers0',
                [
                    'stream0' => 'profile-1',
                    'playhead0' => 1,
                    'event_id0' => '1',
                    'event_name0' => 'profile_created',
                    'event_payload0' => '',
                    'tags0' => [],
                    'recorded_on0' => $recordedOn,
                    'archived0' => false,
                    'custom_headers0' => '[]',
                ],
                [
                    'tags0' => Type::getType(Types::JSON),
                    'recorded_on0' => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    'archived0' => Type::getType(Types::BOOLEAN),
                ],
            )
            ->willThrowException(new UniqueConstraintViolationException(new Exception('foo'), null));

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(UniqueConstraintViolation::class);
        $store->append([$message]);
    }

    public function testQueryWithEmptyQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events ORDER BY events.id ASC',
                [],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query());

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithOnlyEmptySubQueries(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events ORDER BY events.id ASC',
                [],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery()));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithStreamName(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE stream = :param1) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => 'profile-1'],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(streamName: 'profile-1')));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithTags(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE NOT EXISTS(SELECT value FROM JSON_EACH(:param1) WHERE value NOT IN (SELECT value FROM JSON_EACH(tags)))) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => '["foo","bar"]'],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(tags: ['foo', 'bar'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithTagsOnPostgreSQL(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE tags @> :param1::jsonb) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => '["foo"]'],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(tags: ['foo'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithTagsOnMySQL(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE JSON_CONTAINS(tags, :param1)) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => '["foo"]'],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(tags: ['foo'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithTagsOnNotSupportedPlatform(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeQuery');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);
        $connection
            ->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(RuntimeException::class);
        $doctrineDbalStore->query(new Query(new SubQuery(tags: ['foo'])));
    }

    public function testQueryWithEvents(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE event_name IN (:param1)) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => ['profile.created']],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry(['profile.created' => ProfileCreated::class]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(events: [ProfileCreated::class])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithOnlyLastEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT MAX(id) AS id FROM event_store WHERE stream = :param1) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                ['param1' => 'profile-1'],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(5))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->exactly(3))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(new SubQuery(streamName: 'profile-1', onlyLastEvent: true)));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testQueryWithMultipleSubQueries(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM event_store events INNER JOIN (SELECT id FROM (SELECT id FROM event_store WHERE stream = :param1 UNION ALL SELECT id FROM event_store WHERE stream = :param2) j GROUP BY j.id) ej ON ej.id = events.id ORDER BY events.id ASC',
                [
                    'param1' => 'profile-1',
                    'param2' => 'profile-2',
                ],
                $this->isArray(),
            )
            ->willReturn($result);

        $connection
            ->expects($this->exactly(6))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->exactly(4))
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->query(new Query(
            new SubQuery(streamName: 'profile-1'),
            new SubQuery(streamName: 'profile-2'),
        ));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithEmptyStreamCriterion(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store ORDER BY id ASC', [], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new StreamCriterion()));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithToPlayhead(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE playhead < :to_playhead ORDER BY id ASC', ['to_playhead' => 10], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->toPlayhead(10)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithToIndex(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE id < :to_index ORDER BY id ASC', ['to_index' => 100], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new ToIndexCriterion(100)));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithEvents(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE event_name IN (:events) ORDER BY id ASC', [
                'events' => ['profile.created'],
            ], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->events(['profile.created'])
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithEventId(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE event_id = :event_id ORDER BY id ASC', ['event_id' => '1'], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new EventIdCriterion('1')));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithTag(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE NOT EXISTS(SELECT value FROM JSON_EACH(:tags) WHERE value NOT IN (SELECT value FROM JSON_EACH(tags))) ORDER BY id ASC', ['tags' => '["foo"]'], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new TagCriterion(['foo'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithTagOnPostgreSQL(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE tags @> :tags::jsonb ORDER BY id ASC', ['tags' => '["foo"]'], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new TagCriterion(['foo'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithTagOnMySQL(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('iterateAssociative')
            ->willReturn(new EmptyIterator());

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store WHERE JSON_CONTAINS(tags, :tags) ORDER BY id ASC', ['tags' => '["foo"]'], $this->isArray())
            ->willReturn($result);

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load(new Criteria(new TagCriterion(['foo'])));

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithTagOnNotSupportedPlatform(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeQuery');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(RuntimeException::class);
        $doctrineDbalStore->load(new Criteria(new TagCriterion(['foo'])));
    }

    public function testLoadWithUnsupportedCriterion(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeQuery');

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(UnsupportedCriterion::class);
        $doctrineDbalStore->load(new Criteria(new stdClass()));
    }

    public function testStreams(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['foo', 'profile-1']);

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT DISTINCT stream FROM event_store ORDER BY stream', [], [])
            ->willReturn($result);

        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        self::assertSame(['foo', 'profile-1'], $doctrineDbalStore->streams());
    }

    public function testRemove(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DELETE FROM event_store', [], [])
            ->willReturn(1);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $doctrineDbalStore->remove();
    }

    public function testRemoveWithCriteria(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DELETE FROM event_store WHERE stream = :stream_0', ['stream_0' => 'profile-1'], $this->isArray())
            ->willReturn(1);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $connection
            ->expects($this->once())
            ->method('createExpressionBuilder')
            ->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $doctrineDbalStore->remove(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->build(),
        );
    }

    public function testArchive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE event_store SET archived = :value', ['value' => true], $this->isArray())
            ->willReturn(1);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $doctrineDbalStore->archive();
    }

    public function testArchiveWithCriteria(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE event_store SET archived = :value WHERE stream = :stream_0', [
                'stream_0' => 'profile-1',
                'value' => true,
            ], $this->isArray())
            ->willReturn(1);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $connection
            ->expects($this->once())
            ->method('createExpressionBuilder')
            ->willReturn(new ExpressionBuilder($connection));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $doctrineDbalStore->archive(
            (new CriteriaBuilder())
                ->streamName('profile-1')
                ->build(),
        );
    }

    public function testSaveWithoutStreamNameHeader(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)", ['main', 1, '1', 'profile_created', '', [], $recordedOn, false, '[]'], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save($message);
    }

    public function testSaveWithCustomDefaultStreamName(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)", ['custom', 1, '1', 'profile_created', '', [], $recordedOn, false, '[]'], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['default_stream_name' => 'custom'],
        );
        $store->save($message);
    }

    public function testSaveWithTags(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new TagsHeader(['foo', 'bar']));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', ['foo', 'bar'], $recordedOn, false, '[]'], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save($message);
    }

    public function testSaveWithKeepIndex(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new IndexHeader(42));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with("INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers, id) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", ['profile-1', 1, '1', 'profile_created', '', [], $recordedOn, false, '[]', 42], [
                5 => Type::getType(Types::JSON),
                6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                7 => Type::getType(Types::BOOLEAN),
            ]);

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['keep_index' => true],
        );
        $store->save($message);
    }

    public function testSaveWithKeepIndexMissingHeader(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->never())
            ->method('executeStatement');

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['keep_index' => true],
        );

        $this->expectException(MissingDataForStorage::class);
        $store->save($message);
    }

    public function testSaveWithKeepIndexOnPostgres(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new EventIdHeader('1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader($recordedOn))
            ->withHeader(new IndexHeader(42));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(new ReturnCallback([
                [
                    [
                        "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers, id) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        ['profile-1', 1, '1', 'profile_created', '', [], $recordedOn, false, '[]', 42],
                        [
                            5 => Type::getType(Types::JSON),
                            6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                            7 => Type::getType(Types::BOOLEAN),
                        ],
                    ],
                    1,
                ],
                [
                    ["SELECT setval('event_store_id_seq', (SELECT MAX(id) FROM event_store));", [], []],
                    1,
                ],
            ]));

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['keep_index' => true, 'locking' => false],
        );
        $store->save($message);
    }

    public function testLoadWithTagsAndArchivedHeader(): void
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
                        'tags' => '["foo"]',
                        'recorded_on' => '2021-02-17 10:00:00',
                        'archived' => '1',
                        'custom_headers' => '[]',
                    ],
                ],
            ));

        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM event_store ORDER BY id ASC', [], $this->isArray())
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
            ->expects($this->once())
            ->method('getDateTimeTzFormatString')
            ->willReturn('Y-m-d H:i:s');

        $connection
            ->expects($this->exactly(3))
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'))
            ->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('[]')
            ->willReturn([]);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $stream = $doctrineDbalStore->load();
        $message = $stream->current();

        self::assertInstanceOf(Message::class, $message);
        self::assertSame(['foo'], $message->header(TagsHeader::class)->tags);
        self::assertTrue($message->hasHeader(ArchivedHeader::class));
    }

    public function testSupportSubscription(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        self::assertTrue($doctrineDbalStore->supportSubscription());
    }

    public function testSupportSubscriptionNotPostgres(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        self::assertFalse($doctrineDbalStore->supportSubscription());
    }

    public function testTransactionalWithoutLocking(): void
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
            ->expects($this->never())
            ->method('fetchOne');
        $connection
            ->expects($this->never())
            ->method('executeStatement');

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['locking' => false],
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithCustomLockId(): void
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
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                ['SELECT GET_LOCK("42", -1)', 1],
                ['SELECT RELEASE_LOCK("42")', 1],
            ]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['lock_id' => 42],
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testTransactionalWithPostgreSQLCustomLockId(): void
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
            ->willReturn(new PostgreSQLPlatform());

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('SELECT pg_advisory_xact_lock(42)');

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            config: ['lock_id' => 42],
        );

        $store->transactional($callback(...));

        self::assertTrue($callback->called);
    }

    public function testSaveWithNoMessages(): void
    {
        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->never())
            ->method('transactional');
        $mockedConnection
            ->expects($this->never())
            ->method('executeStatement');

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save();
    }

    public function testSaveWithHeaderFallbacks(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message->event())
            ->willReturn(new SerializedEvent(
                'profile_created',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                "INSERT INTO event_store (stream, playhead, event_id, event_name, event_payload, tags, recorded_on, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $this->callback(static function (array $parameters) use ($now): bool {
                    return $parameters[0] === 'main'
                        && $parameters[1] === null
                        && is_string($parameters[2])
                        && Uuid::isValid($parameters[2])
                        && $parameters[3] === 'profile_created'
                        && $parameters[4] === ''
                        && $parameters[5] === []
                        && $parameters[6] === $now
                        && $parameters[7] === false
                        && $parameters[8] === '[]';
                }),
                [
                    5 => Type::getType(Types::JSON),
                    6 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                    7 => Type::getType(Types::BOOLEAN),
                ],
            );

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            $clock,
        );
        $store->save($message);
    }

    public function testSaveWithExactBatchSize(): void
    {
        $recordedOn = new DateTimeImmutable();

        $messages = [];
        for ($i = 1; $i <= 7281; $i++) {
            $messages[] = Message::create(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('s')))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader($i))
                ->withHeader(new RecordedOnHeader($recordedOn));
        }

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(7281))
            ->method('serialize')
            ->with($messages[0]->event())
            ->willReturn(new SerializedEvent(
                'profile_email_changed',
                '',
            ));

        $eventRegistry = new EventRegistry([]);

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(7281))
            ->method('serialize')
            ->with([])
            ->willReturn('[]');

        $mockedConnection = $this->createMock(Connection::class);
        $mockedConnection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $mockedConnection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(
                static fn (Closure $closure): mixed => $closure(),
            );

        $mockedConnection
            ->expects($this->once())
            ->method('executeStatement');

        $store = new TaggableDoctrineDbalStore(
            $mockedConnection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $store->save(...$messages);
    }

    public function testWaitNotPostgres(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->never())
            ->method('executeStatement');
        $connection
            ->expects($this->never())
            ->method('getNativeConnection');

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $doctrineDbalStore->wait(100);
    }

    public function testTransactionalLockingNotImplemented(): void
    {
        $callback = new class () {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $abstractPlatform = $this->createMock(AbstractPlatform::class);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);
        $connection
            ->expects($this->never())
            ->method('fetchOne');
        $connection
            ->expects($this->never())
            ->method('executeStatement');
        $connection
            ->expects($this->once())
            ->method('transactional')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $store = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $this->expectException(LockingNotImplemented::class);
        $store->transactional($callback(...));
    }

    public function testCount(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived)', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
            ->willReturn('1');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform
            ->expects($this->once())
            ->method('createSelectSQLBuilder')
            ->willReturn(new DefaultSelectSQLBuilder(
                $abstractPlatform,
                'FOR UPDATE',
                'SKIP LOCKED',
            ));
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM event_store WHERE (stream = :stream_0) AND (playhead > :from_playhead) AND (archived = :archived)', [
                'stream_0' => 'profile-1',
                'from_playhead' => 0,
                'archived' => false,
            ], $this->isArray())
            ->willReturn([]);

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform
            ->expects($this->once())
            ->method('createSelectSQLBuilder')
            ->willReturn(new DefaultSelectSQLBuilder(
                $abstractPlatform,
                'FOR UPDATE',
                'SKIP LOCKED',
            ));
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
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $clock = $this->createMock(ClockInterface::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
            $clock,
            ['table_name' => 'new.event_store'],
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testSetupSubscriptionNotPostgres(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($abstractPlatform);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );
        $doctrineDbalStore->wait(100);
    }

    public function testConfigureSchemaWithDifferentConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
            $headersSerializer,
        );

        $differentConnection = $this->createMock(Connection::class);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $differentConnection);

        self::assertEquals(new Schema(), $schema);
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventRegistry = new EventRegistry([]);
        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $doctrineDbalStore = new TaggableDoctrineDbalStore(
            $connection,
            $eventSerializer,
            $eventRegistry,
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
        $table->addColumn('tags', Types::JSON)
            ->setLength(255)
            ->setNotnull(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setColumnNames(
                UnqualifiedName::unquoted('id'),
            )->create(),
        );
        $table->addUniqueIndex(['event_id']);
        $table->addUniqueIndex(['stream', 'playhead']);
        $table->addIndex(['stream', 'playhead', 'archived']);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $connection);

        self::assertEquals($expectedSchema, $schema);
    }
}
