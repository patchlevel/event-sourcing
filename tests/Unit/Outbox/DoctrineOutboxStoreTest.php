<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\MessageSerializer;
use Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore;
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
            ['message' => 'serialized'],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $serializer = $this->prophesize(MessageSerializer::class);
        $serializer->serialize($message)->shouldBeCalledOnce()->willReturn('serialized');

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
            ->withArchived(false)
            ->withCustomHeader(DoctrineOutboxStore::HEADER_OUTBOX_IDENTIFIER, 42);

        $innerMockedConnection = $this->prophesize(Connection::class);
        $innerMockedConnection->delete(
            'outbox',
            ['id' => 42],
        )->shouldBeCalledOnce();

        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::any())->willReturn($innerMockedConnection->reveal());

        $mockedConnection = $this->prophesize(Connection::class);
        $mockedConnection->transactional(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0]($innerMockedConnection->reveal())
        );

        $serializer = $this->prophesize(MessageSerializer::class);

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

        $serializer = $this->prophesize(MessageSerializer::class);

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

        $serializer = $this->prophesize(MessageSerializer::class);

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

        $serializer = $this->prophesize(MessageSerializer::class);

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
            ->withArchived(false)
            ->withCustomHeader(DoctrineOutboxStore::HEADER_OUTBOX_IDENTIFIER, 42);

        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->select('*')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->from('outbox')->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->setMaxResults(null)->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $queryBuilder->getSQL()->shouldBeCalledOnce()->willReturn('this sql');

        $connection = $this->prophesize(Connection::class);
        $connection->createQueryBuilder()->shouldBeCalledOnce()->willReturn($queryBuilder->reveal());
        $connection->fetchAllAssociative('this sql')->shouldBeCalledOnce()->willReturn([
            [
                'id' => 42,
                'message' => 'serialized',
            ],
        ]);

        $serializer = $this->prophesize(MessageSerializer::class);
        $serializer->deserialize('serialized')->shouldBeCalledOnce()->willReturn($message);

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

        $serializer = $this->prophesize(MessageSerializer::class);

        $doctrineOutboxStore = new DoctrineOutboxStore(
            $connection->reveal(),
            $serializer->reveal(),
        );

        $table = $this->prophesize(Table::class);
        $column = $this->prophesize(Column::class);
        $table->addColumn('id', Types::INTEGER)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->setAutoincrement(true)->shouldBeCalledOnce()->willReturn($column->reveal())
                    ->getObjectProphecy()->reveal(),
            );

        $column = $this->prophesize(Column::class);
        $table->addColumn('message', Types::TEXT)->shouldBeCalledOnce()
            ->willReturn(
                $column
                    ->setNotnull(true)->shouldBeCalledOnce()
                    ->getObjectProphecy()->setLength(16_000)->shouldBeCalledOnce()->willReturn($column->reveal())
                    ->getObjectProphecy()->reveal(),
            );

        $table->setPrimaryKey(['id'])->shouldBeCalledOnce();

        $schema = $this->prophesize(Schema::class);
        $schema->createTable('outbox')->shouldBeCalledOnce()->willReturn($table->reveal());

        $doctrineOutboxStore->configureSchema($schema->reveal(), $connection->reveal());
    }
}
