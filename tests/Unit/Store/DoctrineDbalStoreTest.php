<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use ArrayIterator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\SelectQuery;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use EmptyIterator;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\SerializedHeader;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

use function iterator_to_array;

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
        $selectSqlBuilder = $this->prophesize(SelectSQLBuilder::class);

        $selectSqlBuilder->buildSQL(Argument::type(SelectQuery::class))
            ->willReturn('SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC');

        $abstractPlatform->createSelectSQLBuilder()
            ->willReturn($selectSqlBuilder->reveal());

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());
        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            'eventstore',
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
        $selectSqlBuilder = $this->prophesize(SelectSQLBuilder::class);

        $selectSqlBuilder->buildSQL(Argument::type(SelectQuery::class))
            ->willReturn('SELECT * FROM eventstore WHERE (aggregate = :aggregate) AND (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived) ORDER BY id ASC');

        $abstractPlatform->createSelectSQLBuilder()->shouldBeCalledOnce()->willReturn($selectSqlBuilder->reveal());
        $abstractPlatform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');
        $abstractPlatform->convertFromBoolean('0')->shouldBeCalledTimes(2)->willReturn(false);

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->deserialize(
            new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'),
        )->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->deserialize([])->willReturn([]);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            'eventstore',
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
        self::assertEquals(new DateTimeImmutable('2021-02-17 10:00:00'), $message->header(AggregateHeader::class)->recordedOn);

        iterator_to_array($stream);

        self::assertSame(1, $stream->index());
        self::assertSame(0, $stream->position());
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $connection = $this->prophesize(Connection::class);
        $connection->transactional($callback)->willReturn(null)->shouldBeCalled();

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            'eventstore',
        );

        $store->transactional($callback);
    }

    public function testSaveWithOneEvent(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withHeader(new AggregateHeader(
                'profile',
                '1',
                1,
                $recordedOn
            ));

        $innerMockedConnection = $this->prophesize(Connection::class);

        $innerMockedConnection->executeStatement(
            "INSERT INTO eventstore (aggregate, aggregate_id, playhead, event, payload, recorded_on, new_stream_start, archived, custom_headers) VALUES\n(?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['profile', '1', 1, 'profile_created', '', $recordedOn, false, false, []],
            [
                5 => Type::getType(Types::DATETIMETZ_IMMUTABLE),
                6 => Type::getType(Types::BOOLEAN),
                7 => Type::getType(Types::BOOLEAN),
                8 => Type::getType(Types::JSON),
            ],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent('profile_created', ''));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([])->willReturn([]);

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            'eventstore',
        );
        $singleTableStore->save($message);
    }

    #[RequiresPhp('>= 8.2')]
    public function testArchiveMessages(): void
    {
        $eventSerializer = $this->prophesize(EventSerializer::class);
        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $statement = $this->prophesize(Statement::class);
        $statement->bindValue('aggregate', 'profile')->shouldBeCalledOnce();
        $statement->bindValue('aggregate_id', '1')->shouldBeCalledOnce();
        $statement->bindValue('playhead', 1)->shouldBeCalledOnce();
        $statement->executeQuery()->shouldBeCalledOnce();

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->prepare(
            'UPDATE eventstore 
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false',
        )->shouldBeCalledOnce()->willReturn($statement->reveal());

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            'eventstore',
        );
        $singleTableStore->archiveMessages('profile', '1', 1);
    }
}
