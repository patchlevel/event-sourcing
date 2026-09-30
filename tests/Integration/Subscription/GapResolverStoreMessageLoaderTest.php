<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GapResolverStoreMessageLoaderTest extends TestCase
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

    public function testLoadAllEventsWithoutGaps(): void
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

        $loader = new GapResolverStoreMessageLoader(
            $store,
            new FrozenClock($recordedOn),
            [0, 0],
        );

        $stream = $loader->load(1, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([2, 3], $indexes);
    }

    public function testEventWrittenIntoGapIsNotSkipped(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
        );

        $loader = new GapResolverStoreMessageLoader(
            $store,
            new FrozenClock($recordedOn),
            [0, 0],
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();

            if ($stream->index() !== 2) {
                continue;
            }

            // simulates a transaction that commits after the stream was loaded
            $store->save(
                Message::create(new ProfileCreated(ProfileId::generate(), 'Late'))
                    ->withHeader(new StreamNameHeader('profile-3'))
                    ->withHeader(new PlayheadHeader(1))
                    ->withHeader(new RecordedOnHeader($recordedOn))
                    ->withHeader(new IndexHeader(3)),
            );
        }

        self::assertSame([1, 2, 3, 4], $indexes);
    }

    public function testPermanentGapIsAcceptedWithoutDuplicates(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(2)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Tom'))
                ->withHeader(new StreamNameHeader('profile-4'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(4)),
            Message::create(new ProfileCreated(ProfileId::generate(), 'Anna'))
                ->withHeader(new StreamNameHeader('profile-5'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(5)),
        );

        $loader = new GapResolverStoreMessageLoader(
            $store,
            new FrozenClock($recordedOn),
            [0, 0],
        );

        $stream = $loader->load(0, [new Subscription('profile_1')]);

        $indexes = [];

        foreach ($stream as $message) {
            $indexes[] = $stream->index();
        }

        self::assertSame([1, 2, 4, 5], $indexes);
    }

    public function testLastIndex(): void
    {
        $recordedOn = new DateTimeImmutable('2020-01-01 00:00:00');

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            config: ['keep_index' => true],
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $store->save(
            Message::create(new ProfileCreated(ProfileId::generate(), 'John'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(1))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(1)),
            Message::create(new NameChanged(ProfileId::generate(), 'Jane'))
                ->withHeader(new StreamNameHeader('profile-1'))
                ->withHeader(new PlayheadHeader(2))
                ->withHeader(new RecordedOnHeader($recordedOn))
                ->withHeader(new IndexHeader(5)),
        );

        $loader = new GapResolverStoreMessageLoader($store, new FrozenClock($recordedOn));

        self::assertSame(5, $loader->lastIndex());
    }

    public function testLastIndexOnEmptyStore(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        $loader = new GapResolverStoreMessageLoader($store, new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertSame(0, $loader->lastIndex());
    }
}
