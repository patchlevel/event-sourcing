<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Patchlevel\EventSourcing\Store\MultiTableStore
 * @covers \Patchlevel\EventSourcing\Store\DoctrineStore
 */
final class MultiTableStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testLoadWithNoEvents(): void
    {
        $queryBuilder = new QueryBuilder($this->prophesize(Connection::class)->reveal());

        $connection = $this->prophesize(Connection::class);
        $connection->fetchAllAssociative(
            'SELECT * FROM profile WHERE (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived)',
            [
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            [
                'archived' => Types::BOOLEAN,
            ]
        )->willReturn([]);
        $connection->createQueryBuilder()->willReturn($queryBuilder);
        $connection->getDatabasePlatform()->willReturn($this->prophesize(AbstractPlatform::class)->reveal());

        $serializer = $this->prophesize(EventSerializer::class);

        $singleTableStore = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
        );

        $events = $singleTableStore->load(Profile::class, '1');
        self::assertCount(0, $events);
    }

    public function testLoadWithOneEvent(): void
    {
        $queryBuilder = new QueryBuilder($this->prophesize(Connection::class)->reveal());

        $connection = $this->prophesize(Connection::class);
        $connection->fetchAllAssociative(
            'SELECT * FROM profile WHERE (aggregate_id = :id) AND (playhead > :playhead) AND (archived = :archived)',
            [
                'id' => '1',
                'playhead' => 0,
                'archived' => false,
            ],
            [
                'archived' => Types::BOOLEAN,
            ]
        )->willReturn(
            [
                [
                    'aggregate_id' => '1',
                    'playhead' => '1',
                    'event' => 'profile.created',
                    'payload' => '{"profileId": "1", "email": "s"}',
                    'recorded_on' => '2021-02-17 10:00:00',
                    'custom_headers' => '[]',
                ],
            ]
        );

        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->getDateTimeTzFormatString()->willReturn('Y-m-d H:i:s');
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->deserialize(
            new SerializedEvent('profile.created', '{"profileId": "1", "email": "s"}'),
        )->willReturn(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')));

        $singleTableStore = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
        );

        $messages = $singleTableStore->load(Profile::class, '1');
        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertInstanceOf(ProfileCreated::class, $message->event());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(1, $message->playhead());
        self::assertEquals(new DateTimeImmutable('2021-02-17 10:00:00'), $message->recordedOn());
    }

    public function testTransactionBegin(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->beginTransaction()->shouldBeCalled();

        $serializer = $this->prophesize(EventSerializer::class);

        $store = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
        );

        $store->transactionBegin();
    }

    public function testTransactionCommit(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->commit()->shouldBeCalled();

        $serializer = $this->prophesize(EventSerializer::class);

        $store = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
        );

        $store->transactionCommit();
    }

    public function testTransactionRollback(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->rollBack()->shouldBeCalled();

        $serializer = $this->prophesize(EventSerializer::class);

        $store = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
        );

        $store->transactionRollback();
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $connection = $this->prophesize(Connection::class);
        $connection->transactional($callback)->shouldBeCalled();

        $serializer = $this->prophesize(EventSerializer::class);

        $store = new MultiTableStore(
            $connection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore'
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

        $platform = $this->prophesize(AbstractPlatform::class);
        $innerMockedConnection->getDatabasePlatform()->willReturn($platform->reveal());

        $innerMockedConnection->insert(
            'eventstore_metadata',
            [
                'aggregate' => 'profile',
                'aggregate_id' => '1',
                'playhead' => 1,
            ]
        )->shouldBeCalledOnce();

        $innerMockedConnection->lastInsertId()->shouldBeCalledOnce()->willReturn('2');

        $innerMockedConnection->insert(
            'profile',
            [
                'id' => '2',
                'aggregate_id' => '1',
                'playhead' => 1,
                'event' => 'profile_created',
                'payload' => '',
                'recorded_on' => $recordedOn,
                'new_stream_start' => false,
                'archived' => false,
                'custom_headers' => [],
            ],
            [
                'recorded_on' => 'datetimetz_immutable',
                'custom_headers' => 'json',
                'archived' => Types::BOOLEAN,
                'new_stream_start' => Types::BOOLEAN,
            ],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent('profile_created', ''));

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
            /**
             * @param array{0: callable} $args
             */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $multiTableStore = new MultiTableStore(
            $mockedConnection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore_metadata'
        );
        $multiTableStore->save($message);
    }

    public function testArchiveMessages(): void
    {
        $serializer = $this->prophesize(EventSerializer::class);

        $statement = $this->prophesize(Statement::class);
        $statement->bindValue('aggregate_id', '1')->shouldBeCalledOnce();
        $statement->bindValue('playhead', 1)->shouldBeCalledOnce();
        $statement->executeQuery()->shouldBeCalledOnce();

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->prepare(
            'UPDATE profile 
            SET archived = true
            WHERE aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false'
        )->shouldBeCalledOnce()->willReturn($statement->reveal());

        $multiTableStore = new MultiTableStore(
            $mockedConnection->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore_metadata'
        );
        $multiTableStore->archiveMessages(Profile::class, '1', 1);
    }
}
