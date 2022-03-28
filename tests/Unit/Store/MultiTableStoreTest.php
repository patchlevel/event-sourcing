<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Store\MultiTableStore */
final class MultiTableStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testLoadWithNoEvents(): void
    {
        $queryBuilder = new QueryBuilder($this->prophesize(Connection::class)->reveal());

        $connection = $this->prophesize(Connection::class);
        $connection->fetchAllAssociative(
            'SELECT * FROM profile WHERE aggregate_id = :id AND playhead > :playhead',
            [
                'id' => '1',
                'playhead' => 0,
            ]
        )->willReturn([]);
        $connection->createQueryBuilder()->willReturn($queryBuilder);
        $connection->getDatabasePlatform()->willReturn($this->prophesize(AbstractPlatform::class)->reveal());

        $singleTableStore = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
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
            'SELECT * FROM profile WHERE aggregate_id = :id AND playhead > :playhead',
            [
                'id' => '1',
                'playhead' => 0,
            ]
        )->willReturn(
            [
                [
                    'aggregate_id' => '1',
                    'playhead' => '0',
                    'event' => ProfileCreated::class,
                    'payload' => '{"profileId": "1", "email": "s"}',
                    'recorded_on' => '2021-02-17 10:00:00',
                ],
            ]
        );

        $connection->createQueryBuilder()->willReturn($queryBuilder);

        $abstractPlatform = $this->prophesize(AbstractPlatform::class);
        $abstractPlatform->getDateTimeTzFormatString()->willReturn('Y-m-d H:i:s');
        $connection->getDatabasePlatform()->willReturn($abstractPlatform->reveal());

        $singleTableStore = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
            'eventstore'
        );

        $messages = $singleTableStore->load(Profile::class, '1');
        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertInstanceOf(ProfileCreated::class, $message->event());
        self::assertSame('1', $message->aggregateId());
        self::assertSame(0, $message->playhead());
        self::assertEquals(new DateTimeImmutable('2021-02-17 10:00:00'), $message->recordedOn());
    }

    public function testTransactionBegin(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->beginTransaction()->shouldBeCalled();

        $store = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
            'eventstore'
        );

        $store->transactionBegin();
    }

    public function testTransactionCommit(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->commit()->shouldBeCalled();

        $store = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
            'eventstore'
        );

        $store->transactionCommit();
    }

    public function testTransactionRollback(): void
    {
        $connection = $this->prophesize(Connection::class);
        $connection->rollBack()->shouldBeCalled();

        $store = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
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

        $store = new MultiTableStore(
            $connection->reveal(),
            [Profile::class => 'profile'],
            'eventstore'
        );

        $store->transactional($callback);
    }
}
