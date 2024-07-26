<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use PHPUnit\Framework\TestCase;

use function json_decode;

/** @coversNothing */
final class DoctrineDbalStoreTest extends TestCase
{
    private Connection $connection;
    private Store $store;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
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
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    1,
                    new DateTimeImmutable('2020-01-01 00:00:00'),
                )),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    2,
                    new DateTimeImmutable('2020-01-02 00:00:00'),
                )),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM eventstore');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals($profileId->toString(), $result1['aggregate_id']);
        self::assertEquals('profile', $result1['aggregate']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event']);
        self::assertEquals(['profileId' => $profileId->toString(), 'name' => 'test'], json_decode($result1['payload'], true));

        $result2 = $result[1];

        self::assertEquals($profileId->toString(), $result2['aggregate_id']);
        self::assertEquals('profile', $result2['aggregate']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event']);
        self::assertEquals(['profileId' => $profileId->toString(), 'name' => 'test'], json_decode($result1['payload'], true));
    }

    public function testSaveWithTransactional(): void
    {
        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    1,
                    new DateTimeImmutable('2020-01-01 00:00:00'),
                )),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    2,
                    new DateTimeImmutable('2020-01-02 00:00:00'),
                )),
        ];

        $this->store->transactional(function () use ($messages): void {
            $this->store->save(...$messages);
        });

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM eventstore');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals($profileId->toString(), $result1['aggregate_id']);
        self::assertEquals('profile', $result1['aggregate']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event']);
        self::assertEquals(['profileId' => $profileId->toString(), 'name' => 'test'], json_decode($result1['payload'], true));

        $result2 = $result[1];

        self::assertEquals($profileId->toString(), $result2['aggregate_id']);
        self::assertEquals('profile', $result2['aggregate']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event']);
        self::assertEquals(['profileId' => $profileId->toString(), 'name' => 'test'], json_decode($result1['payload'], true));
    }

    public function testUniqueConstraint(): void
    {
        $this->expectException(UniqueConstraintViolation::class);

        $profileId = ProfileId::generate();

        $messages = [
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    1,
                    new DateTimeImmutable('2020-01-01 00:00:00'),
                )),
            Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader(
                    'profile',
                    $profileId->toString(),
                    1,
                    new DateTimeImmutable('2020-01-02 00:00:00'),
                )),
        ];

        $this->store->save(...$messages);
    }

    public function testSave10000Messages(): void
    {
        $profileId = ProfileId::generate();

        $messages = [];

        for ($i = 1; $i <= 10000; $i++) {
            $messages[] = Message::create(new ProfileCreated($profileId, 'test'))
                ->withHeader(new AggregateHeader('profile', $profileId->toString(), $i, new DateTimeImmutable('2020-01-01 00:00:00')));
        }

        $this->store->save(...$messages);

        /** @var int $result */
        $result = $this->connection->fetchFirstColumn('SELECT COUNT(*) FROM eventstore')[0];

        self::assertEquals(10000, $result);
    }

    public function testLoad(): void
    {
        $profileId = ProfileId::generate();

        $message = Message::create(new ProfileCreated($profileId, 'test'))
            ->withHeader(new AggregateHeader(
                'profile',
                $profileId->toString(),
                1,
                new DateTimeImmutable('2020-01-01 00:00:00'),
            ));

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
            self::assertEquals($message->header(AggregateHeader::class)->aggregateId, $loadedMessage->header(AggregateHeader::class)->aggregateId);
            self::assertEquals($message->header(AggregateHeader::class)->aggregateName, $loadedMessage->header(AggregateHeader::class)->aggregateName);
            self::assertEquals($message->header(AggregateHeader::class)->playhead, $loadedMessage->header(AggregateHeader::class)->playhead);
            self::assertEquals($message->header(AggregateHeader::class)->recordedOn, $loadedMessage->header(AggregateHeader::class)->recordedOn);
        } finally {
            $stream?->close();
        }
    }
}
