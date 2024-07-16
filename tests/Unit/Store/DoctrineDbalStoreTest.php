<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use ArrayIterator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use EmptyIterator;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\MissingDataForStorage;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Store\WrongQueryResult;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileEmailChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

use function iterator_to_array;
use function method_exists;

/** @covers \Patchlevel\EventSourcing\Store\DoctrineDbalStore */
final class DoctrineDbalStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testLoadWithNoEvents(): void
    {
        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new EmptyIterator());

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());
        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithLimit(): void
    {
        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new EmptyIterator());

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC LIMIT 10',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());
        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
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

        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new EmptyIterator());

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC OFFSET 5',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());
        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
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
        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new EmptyIterator());

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (id > :index) AND (archived = :archived) ORDER BY id ASC',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
                'index' => 1,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());
        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
                ->fromPlayhead(0)
                ->archived(false)
                ->fromIndex(1)
                ->build(),
        );

        self::assertSame(null, $stream->index());
        self::assertSame(null, $stream->position());
    }

    public function testLoadWithOneEvent(): void
    {
        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new ArrayIterator(
            [
                [
                    'id' => 1,
                    'aggregate' => 'profile',
                    'aggregate_id' => '1',
                    'playhead' => '1',
                    'event' => 'profile.created',
                    'payload' => '{"profileId": "1", "email": "s"}',
                    'recorded_on' => '2021-02-17 10:00:00',
                    'archived' => '0',
                    'new_stream_start' => '0',
                    'custom_headers' => '[]',
                ],
            ],
        ));

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);

        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $abstractPlatform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(
            new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'),
        )->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('[]')->willReturn([]);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
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
        self::assertSame('1', $message->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $message->header(AggregateHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 10:00:00'),
            $message->header(AggregateHeader::class)->recordedOn,
        );

        iterator_to_array($stream);

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
    }

    public function testLoadWithTwoEvents(): void
    {
        $connection = $this->prophesize(Connection::class);
        $result = $this->prophesize(Result::class);
        $result->iterateAssociative()->willReturn(new ArrayIterator(
            [
                [
                    'id' => 1,
                    'aggregate' => 'profile',
                    'aggregate_id' => '1',
                    'playhead' => '1',
                    'event' => 'profile.created',
                    'payload' => '{"profileId": "1", "email": "s"}',
                    'recorded_on' => '2021-02-17 10:00:00',
                    'archived' => '0',
                    'new_stream_start' => '0',
                    'custom_headers' => '[]',
                ],
                [
                    'id' => 2,
                    'aggregate' => 'profile',
                    'aggregate_id' => '1',
                    'playhead' => '2',
                    'event' => 'profile.email_changed',
                    'payload' => '{"profileId": "1", "email": "d"}',
                    'recorded_on' => '2021-02-17 11:00:00',
                    'archived' => '0',
                    'new_stream_start' => '0',
                    'custom_headers' => '[]',
                ],
            ],
        ));

        $connection->executeQuery(
            'SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn($result->reveal());

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $abstractPlatform->getDateTimeTzFormatString()->shouldBeCalledTimes(2)->willReturn('Y-m-d H:i:s');

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(
            new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'),
        )->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));
        $eventSerializer->deserialize(
            new SerializedEvent('profile.email_changed', '{"profileId": "1", "email": "d"}'),
        )->willReturn(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('d')));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize('[]')->willReturn([]);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
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
        self::assertSame('1', $message->header(AggregateHeader::class)->aggregateId);
        self::assertSame(1, $message->header(AggregateHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 10:00:00'),
            $message->header(AggregateHeader::class)->recordedOn,
        );

        $stream->next();
        $message = $stream->current();

        self::assertSame(2, $stream->index());
        self::assertSame(1, $stream->position());

        self::assertInstanceOf(Message::class, $message);
        self::assertInstanceOf(ProfileEmailChanged::class, $message->event());
        self::assertSame('1', $message->header(AggregateHeader::class)->aggregateId);
        self::assertSame(2, $message->header(AggregateHeader::class)->playhead);
        self::assertEquals(
            new DateTimeImmutable('2021-02-17 11:00:00'),
            $message->header(AggregateHeader::class)->recordedOn,
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new MySQLPlatform());
        $connection->fetchAllAssociative('SELECT GET_LOCK("133742", -1)')->shouldBeCalledOnce()->willReturn([]);
        $connection->fetchAllAssociative('SELECT RELEASE_LOCK("133742")')->shouldBeCalledOnce()->willReturn([]);

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new MariaDBPlatform());
        $connection->fetchAllAssociative('SELECT GET_LOCK("133742", -1)')->shouldBeCalledOnce()->willReturn([]);
        $connection->fetchAllAssociative('SELECT RELEASE_LOCK("133742")')->shouldBeCalledOnce()->willReturn([]);

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new PostgreSQLPlatform());
        $connection->executeStatement('SELECT pg_advisory_xact_lock(133742)')->shouldBeCalledOnce();

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new PostgreSQLPlatform());
        $connection->executeStatement('SELECT pg_advisory_xact_lock(133742)')->shouldBeCalledOnce();

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalledTimes(2);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new PostgreSQLPlatform());
        $connection->executeStatement('SELECT pg_advisory_xact_lock(133742)')->shouldBeCalledTimes(2);

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new MariaDBPlatform());
        $connection->fetchAllAssociative('SELECT GET_LOCK("133742", -1)')->shouldBeCalledOnce()->willReturn([]);
        $connection->fetchAllAssociative('SELECT RELEASE_LOCK("133742")')->shouldBeCalledOnce()->willReturn([]);

        $connection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $this->expectException(RuntimeException::class);

        $store->transactional($callback(...));
    }

    public function testSaveWithOneEvent(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn,
            ));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['profile', '1', 1, 'profile_created', '', $recordedOn, false, false, '[]'],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $singleTableStore->save($message);
    }

    public function testSaveWithoutAggregateHeader(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['profile', '1', 1, 'profile_created', '', $recordedOn, false, false, []],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldNotBeCalled();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $this->expectException(MissingDataForStorage::class);
        $singleTableStore->save($message);
    }

    public function testSaveWithTwoEvents(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn,
            ));
        $message2 = Message::create(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('d')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                2,
                $recordedOn,
            ));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message1->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));
        $eventSerializer->serialize($message2->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_email_changed',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                'profile',
                '1',
                1,
                'profile_created',
                '',
                $recordedOn,
                false,
                false,
                '[]',
                'profile',
                '1',
                2,
                'profile_email_changed',
                '',
                $recordedOn,
                false,
                false,
                '[]',
            ],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
                14 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                15 => Type::getType(Types::BOOLEAN),
                16 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $singleTableStore->save($message1, $message2);
    }

    public function testSaveWithUniqueConstraintViolation(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn,
            ));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn,
            ));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message1->event())->shouldBeCalledTimes(2)->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                'profile',
                '1',
                1,
                'profile_created',
                '',
                $recordedOn,
                false,
                false,
                '[]',
                'profile',
                '1',
                1,
                'profile_created',
                '',
                $recordedOn,
                false,
                false,
                '[]',
            ],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
                14 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                15 => Type::getType(Types::BOOLEAN),
                16 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce()->willThrow(UniqueConstraintViolationException::class);

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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
                ->withHeader(new AggregateHeader(
                    'profile',
                    '1',
                    $i,
                    $recordedOn,
                ));
        }

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($messages[0]->event())->shouldBeCalledTimes(10000)->willReturn(new SerializedEvent(
            'profile_email_changed',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(Argument::any(), Argument::any(), Argument::any())
            ->shouldBeCalledTimes(2);

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
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
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn,
            ))
            ->withHeaders($customHeaders);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($customHeaders)->willReturn('{foo: "foo", baz: "baz"}');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['profile', '1', 1, 'profile_created', '', $recordedOn, false, false, '{foo: "foo", baz: "baz"}'],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $singleTableStore->save($message);
    }

    public function testCount(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->fetchOne(
            'SELECT COUNT(*) FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived)',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn('1');

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $count = $doctrineDbalStore->count(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );

        self::assertSame(1, $count);
    }

    public function testCountWrongResult(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->fetchOne(
            'SELECT COUNT(*) FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived)',
            [
                'aggregate' => 'profile',
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            Argument::type('array'),
        )->willReturn([]);

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn(new DefaultSelectSQLBuilder(
            $abstractPlatform->reveal(),
            'FOR UPDATE',
            'SKIP LOCKED',
        ));
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $this->expectException(WrongQueryResult::class);
        $doctrineDbalStore->count(
            (new CriteriaBuilder())
                ->aggregateName('profile')
                ->aggregateId('1')
                ->fromPlayhead(0)
                ->archived(false)
                ->build(),
        );
    }

    public function testSetupSubscription(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->executeStatement(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION notify_eventstore() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('eventstore', 'update');
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
        )->shouldBeCalledOnce()->willReturn(1);
        $connection->executeStatement('DROP TRIGGER IF EXISTS notify_trigger ON eventstore;')
            ->shouldBeCalledOnce()->willReturn(1);
        $connection->executeStatement('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON eventstore FOR EACH ROW EXECUTE PROCEDURE notify_eventstore();')
            ->shouldBeCalledOnce()->willReturn(1);

        $abstractPlatform = $this->prophesize(PostgreSQLPlatform::class);
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testSetupSubscriptionWithOtherStoreTableName(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->executeStatement(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION new.notify_eventstore() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('new.eventstore', 'update');
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
        )->shouldBeCalledOnce()->willReturn(1);
        $connection->executeStatement('DROP TRIGGER IF EXISTS notify_trigger ON new.eventstore;')
            ->shouldBeCalledOnce()->willReturn(1);
        $connection->executeStatement('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON new.eventstore FOR EACH ROW EXECUTE PROCEDURE new.notify_eventstore();')
            ->shouldBeCalledOnce()->willReturn(1);

        $abstractPlatform = $this->prophesize(PostgreSQLPlatform::class);
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            ['table_name' => 'new.eventstore'],
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testSetupSubscriptionNotPostgres(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->executeStatement(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION notify_eventstore() RETURNS TRIGGER AS $$
                    BEGIN
                        PERFORM pg_notify('eventstore');
                        RETURN NEW;
                    END;
                $$ LANGUAGE plpgsql;
                SQL,
        )->shouldNotBeCalled();
        $connection->executeStatement('DROP TRIGGER IF EXISTS notify_trigger ON eventstore;')
            ->shouldNotBeCalled();
        $connection->executeStatement('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON eventstore FOR EACH ROW EXECUTE PROCEDURE notify_eventstore();')
            ->shouldNotBeCalled();

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $doctrineDbalStore->setupSubscription();
    }

    public function testWait(): void
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

        $connection = $this->prophesize(Connection::class);
        $connection->executeStatement('LISTEN "eventstore"')
            ->shouldBeCalledOnce()
            ->willReturn(1);
        $connection->getNativeConnection()
            ->shouldBeCalledOnce()
            ->willReturn($nativeConnection);

        $abstractPlatform = $this->prophesize(PostgreSQLPlatform::class);
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $doctrineDbalStore->wait(100);
    }

    public function testConfigureSchemaWithDifferentConnections(): void
    {
        $connection = $this->prophesize(Connection::class);
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );
        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $this->prophesize(Connection::class)->reveal());

        self::assertEquals(new Schema(), $schema);
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->prophesize(Connection::class);
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $expectedSchema = new Schema();
        $table = $expectedSchema->createTable('eventstore');
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregate', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::GUID)
            ->setLength(36)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('new_stream_start', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);
        $table->addIndex(['aggregate', 'aggregate_id', 'playhead', 'archived']);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $connection->reveal());

        self::assertEquals($expectedSchema, $schema);
    }

    public function testConfigureSchemaWithStringAsAggregateIdType(): void
    {
        $connection = $this->prophesize(Connection::class);
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            ['aggregate_id_type' => 'string'],
        );

        $expectedSchema = new Schema();
        $table = $expectedSchema->createTable('eventstore');
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregate', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::STRING)
            ->setLength(36)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('new_stream_start', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);
        $table->addIndex(['aggregate', 'aggregate_id', 'playhead', 'archived']);

        $schema = new Schema();
        $doctrineDbalStore->configureSchema($schema, $connection->reveal());

        self::assertEquals($expectedSchema, $schema);
    }

    #[RequiresPhp('>= 8.2')]
    public function testArchiveMessagesDifferentAggregates(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                5,
                $recordedOn,
            ))
            ->withHeader(new StreamStartHeader());

        $message2 = Message::create(new ProfileEmailChanged(ProfileId::fromString('2'), Email::fromString('d')))
            ->withHeader(new AggregateHeader(
                'profile',
                '2',
                42,
                $recordedOn,
            ))
            ->withHeader(new StreamStartHeader());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message1->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));
        $eventSerializer->serialize($message2->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_email_changed',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                'profile',
                '1',
                5,
                'profile_created',
                '',
                $recordedOn,
                true,
                false,
                '[]',
                'profile',
                '2',
                42,
                'profile_email_changed',
                '',
                $recordedOn,
                true,
                false,
                '[]',
            ],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
                14 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                15 => Type::getType(Types::BOOLEAN),
                16 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce();

        $mockedConnection->executeStatement(
            <<<'SQL'
            UPDATE eventstore
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false
            SQL,
            [
                'aggregate' => 'profile',
                'aggregate_id' => '1',
                'playhead' => 5,
            ],
        )->shouldBeCalledOnce();

        $mockedConnection->executeStatement(
            <<<'SQL'
            UPDATE eventstore
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false
            SQL,
            [
                'aggregate' => 'profile',
                'aggregate_id' => '2',
                'playhead' => 42,
            ],
        )->shouldBeCalledOnce();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $singleTableStore->save($message1, $message2);
    }

    #[RequiresPhp('>= 8.2')]
    public function testArchiveMessagesSameAggregate(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                5,
                $recordedOn,
            ))
            ->withHeader(new StreamStartHeader());

        $message2 = Message::create(new ProfileEmailChanged(ProfileId::fromString('1'), Email::fromString('d')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                42,
                $recordedOn,
            ))
            ->withHeader(new StreamStartHeader());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message1->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_created',
            '',
        ));
        $eventSerializer->serialize($message2->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent(
            'profile_email_changed',
            '',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn('[]');

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->getDatabasePlatform()->willReturn(new SQLitePlatform());
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        );

        $mockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?),\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                'profile',
                '1',
                5,
                'profile_created',
                '',
                $recordedOn,
                true,
                false,
                '[]',
                'profile',
                '1',
                42,
                'profile_email_changed',
                '',
                $recordedOn,
                true,
                false,
                '[]',
            ],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
                14 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                15 => Type::getType(Types::BOOLEAN),
                16 => Type::getType(Types::BOOLEAN),
            ],
        )->shouldBeCalledOnce();

        $mockedConnection->executeStatement(
            <<<'SQL'
            UPDATE eventstore
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false
            SQL,
            [
                'aggregate' => 'profile',
                'aggregate_id' => '1',
                'playhead' => 42,
            ],
        )->shouldBeCalledOnce();

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
        );

        $singleTableStore->save($message1, $message2);
    }
}
