<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\WrongQueryResult;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore */
final class DoctrineOutboxStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveOutboxMessage(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withAggregateName('profile')
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedOn)
            ->withNewStreamStart(false)
            ->withArchived(false);

        $innerMockedConnection = $this->prophesize(Connection::class);
        $innerMockedConnection->insert(
            'outbox',
            [
                'aggregate' => 'profile',
                'aggregate_id' => '1',
                'playhead' => 1,
                'event' => 'profile_created',
                'payload' => '',
                'recorded_on' => $recordedOn,
                'custom_headers' => [],
            ],
            ['recorded_on' => 'datetimetz_immutable', 'custom_headers' => 'json'],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($message->event())->shouldBeCalledOnce()->willReturn(new SerializedEvent('profile_created', ''));

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $mockedConnection->reveal(),
            $serializer->reveal(),
        );

        $doctrineOutboxStore->saveOutboxMessage($message);
    }

    public function testMarkOutboxMessageConsumed(): void
    {
        $recordedOn = new DateTimeImmutable();
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s')))
            ->withAggregateName('profile')
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedOn)
            ->withNewStreamStart(false)
            ->withArchived(false);

        $innerMockedConnection = $this->prophesize(Connection::class);
        $innerMockedConnection->delete(
            'outbox',
            ['aggregate' => 'profile', 'aggregate_id' => '1', 'playhead' => 1],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $serializer = $this->prophesize(EventSerializer::class);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $mockedConnection->reveal(),
            $serializer->reveal(),
        );

        $doctrineOutboxStore->markOutboxMessageConsumed($message);
    }

    public function testCountOutboxMessages(): void
    {
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->select('COUNT(*)')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->from('outbox')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->getSQL()->shouldBeCalledOnce()->willReturn('this sql');

        $connection = $this->prophesize(Connection::class);
        $connection->createQueryBuilder()->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $connection->fetchOne('this sql')->shouldBeCalledOnce()->willReturn('1');

        $serializer = $this->prophesize(EventSerializer::class);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $result = $doctrineOutboxStore->countOutboxMessages();
        self::assertSame(1, $result);
    }

    public function testCountOutboxMessagesFailure(): void
    {
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->select('COUNT(*)')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->from('outbox')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->getSQL()->shouldBeCalledOnce()->willReturn('this sql');

        $connection = $this->prophesize(Connection::class);
        $connection->createQueryBuilder()->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $connection->fetchOne('this sql')->shouldBeCalledOnce()->willReturn([]);

        $serializer = $this->prophesize(EventSerializer::class);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $this->expectException(WrongQueryResult::class);
        $doctrineOutboxStore->countOutboxMessages();
    }

    public function testRetrieveOutboxMessagesNoResult(): void
    {
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->select('*')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->from('outbox')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->setMaxResults(null)->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->getSQL()->shouldBeCalledOnce()->willReturn('this sql');

        $connection = $this->prophesize(Connection::class);
        $connection->createQueryBuilder()->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $connection->fetchAllAssociative('this sql')->shouldBeCalledOnce()->willReturn([]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $connection->getDatabasePlatform()->shouldBeCalledOnce()->willReturn($platform->reveal());

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->deserialize(Argument::type(SerializedEvent::class))->shouldNotBeCalled();

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $messages = $doctrineOutboxStore->retrieveOutboxMessages();
        self::assertSame([], $messages);
    }

    public function testRetrieveOutboxMessages(): void
    {
        $recordedOn = new DateTimeImmutable();
        $event = new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s'));
        $message = Message::create($event)
            ->withAggregateName('profile')
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn($recordedOn)
            ->withNewStreamStart(false)
            ->withArchived(false);

        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->select('*')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->from('outbox')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->setMaxResults(null)->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->getSQL()->shouldBeCalledOnce()->willReturn('this sql');

        $connection = $this->prophesize(Connection::class);
        $connection->createQueryBuilder()->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $connection->fetchAllAssociative('this sql')->shouldBeCalledOnce()->willReturn([
            [
                'event' => 'profile_created',
                'payload' => '{"profile_id": "1", "email": "s"}',
                'aggregate' => 'profile',
                'aggregate_id' => '1',
                'playhead' => 1,
                'recorded_on' => $recordedOn->format('Y-m-d\TH:i:s.ue'),
                'custom_headers' => '{}',
            ],
        ]);

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d\TH:i:s.ue');

        $connection->getDatabasePlatform()->shouldBeCalledOnce()->willReturn($platform->reveal());

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->deserialize(new SerializedEvent('profile_created', '{"profile_id": "1", "email": "s"}'))->shouldBeCalledOnce()->willReturn($event);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $messages = $doctrineOutboxStore->retrieveOutboxMessages();
        self::assertEquals([$message], $messages);
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->prophesize(Connection::class);
        $serializer = $this->prophesize(EventSerializer::class);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $table = $this->prophesize(Table::class);
        $column = $this->prophesize(Column::class);
        $table->addColumn('aggregate', Types::STRING)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->setLength(255)->shouldBeCalledOnce()->willReturn($column->reveal())
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('aggregate_id', Types::STRING)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->setLength(32)->shouldBeCalledOnce()->willReturn($column->reveal())
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('playhead', Types::INTEGER)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('event', Types::STRING)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->setLength(255)->shouldBeCalledOnce()->willReturn($column->reveal())
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('payload', Types::JSON)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(false)->shouldBeCalledOnce()
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('custom_headers', Types::JSON)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->reveal(),
            );

        $table->setPrimaryKey(['aggregate', 'aggregate_id', 'playhead'])->shouldBeCalledOnce();

        $schema = $this->prophesize(Schema::class);
        $schema->createTable('outbox')->shouldBeCalledOnce()->willReturn($table->reveal());

        $doctrineOutboxStore->configureSchema($schema->reveal(), $connection->reveal());
    }
}
