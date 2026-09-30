<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class StoreMessageLoaderTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testLoadAllEvents(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-2'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader($store);

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2, 3], $indexes);
    }

    public function testLoadFromStartIndex(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-2'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader($store);

        $stream = $loader->load(1, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([2, 3], $indexes);
    }

    public function testLastIndex(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn)),
        );

        $loader = new StoreMessageLoader($store);

        self::assertSame(2, $loader->lastIndex());
    }

    public function testLastIndexOnEmptyStore(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $loader = new StoreMessageLoader($store);

        self::assertSame(0, $loader->lastIndex());
    }
}
