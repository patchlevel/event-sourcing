<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use PHPUnit\Framework\TestCase;

use function json_decode;

/** @coversNothing */
final class StoreTest extends TestCase
{
    private Connection $connection;
    private Store $store;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            new AggregateRootRegistry(['profile' => Profile::class]),
            'eventstore',
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
        $messages = [
            Message::create(new ProfileCreated(ProfileId::fromString('test'), 'test'))
                ->withAggregateClass(Profile::class)
                ->withAggregateId('test')
                ->withPlayhead(1)
                ->withRecordedOn(new DateTimeImmutable('2020-01-01 00:00:00')),
            Message::create(new ProfileCreated(ProfileId::fromString('test'), 'test'))
                ->withAggregateClass(Profile::class)
                ->withAggregateId('test')
                ->withPlayhead(2)
                ->withRecordedOn(new DateTimeImmutable('2020-01-02 00:00:00')),
        ];

        $this->store->save(...$messages);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative('SELECT * FROM eventstore');

        self::assertCount(2, $result);

        $result1 = $result[0];

        self::assertEquals('test', $result1['aggregate_id']);
        self::assertEquals('profile', $result1['aggregate']);
        self::assertEquals('1', $result1['playhead']);
        self::assertStringContainsString('2020-01-01 00:00:00', $result1['recorded_on']);
        self::assertEquals('profile.created', $result1['event']);
        self::assertEquals(['profileId' => 'test', 'name' => 'test'], json_decode($result1['payload'], true));

        $result2 = $result[1];

        self::assertEquals('test', $result2['aggregate_id']);
        self::assertEquals('profile', $result2['aggregate']);
        self::assertEquals('2', $result2['playhead']);
        self::assertStringContainsString('2020-01-02 00:00:00', $result2['recorded_on']);
        self::assertEquals('profile.created', $result2['event']);
        self::assertEquals(['profileId' => 'test', 'name' => 'test'], json_decode($result1['payload'], true));
    }
}
