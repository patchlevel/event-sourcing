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
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
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

        $serializer = $this->prophesize(EventSerializer::class);

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateClass(Profile::class)
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

        $abstractPlatform->createSelectSQLBuilder()
            ->willReturn($selectSqlBuilder->reveal());
        $abstractPlatform->getDateTimeTzFormatString()->willReturn('Y-m-d H:i:s');

        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $queryBuilder = new QueryBuilder($connection->reveal());
        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->deserialize(
            new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'),
        )->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $doctrineDbalStore = new DoctrineDbalStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
        );

        $stream = $doctrineDbalStore->load(
            (new CriteriaBuilder())
                ->aggregateClass(Profile::class)
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
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals(new DateTimeImmutable('2021-02-17 10:00:00'), $message->recordedOn());

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

        $serializer = $this->prophesize(EventSerializer::class);

        $store = new DoctrineDbalStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
        );

        $store->transactional($callback);
    }

    public function testSaveWithOneEvent(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withAggregateClass(Profile::class)
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedOn)
            ->withNewStreamStart(false)
            ->withArchived(false);

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

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent('profile_created', ''));

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $singleTableStore = new DoctrineDbalStore(
            $mockedConnection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
        );
        $singleTableStore->save($message);
    }

    #[RequiresPhp('>= 8.2')]
    public function testArchiveMessages(): void
    {
        $serializer = $this->prophesize(EventSerializer::class);

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
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
        );
        $singleTableStore->archiveMessages(Profile::class, '1', 1);
    }
}
