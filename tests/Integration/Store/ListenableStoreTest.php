<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\ListenableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function hrtime;
use function sprintf;
use function usleep;

#[CoversNothing]
final class ListenableStoreTest extends TestCase
{
    private Connection $listenerConnection;
    private Connection $writerConnection;
    private ProfileId $profileId;

    public function setUp(): void
    {
        $this->listenerConnection = DbalManager::createConnection();

        if (!$this->listenerConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->listenerConnection->close();

            self::markTestSkipped('LISTEN/NOTIFY is only supported by PostgreSQL');
        }

        $this->writerConnection = DriverManager::getConnection($this->listenerConnection->getParams());
        $this->profileId = ProfileId::generate();
    }

    public function tearDown(): void
    {
        $this->listenerConnection->close();

        if (!isset($this->writerConnection)) {
            return;
        }

        $this->writerConnection->close();
    }

    /** @return iterable<string, array{Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator)}> */
    public static function storeProvider(): iterable
    {
        yield 'stream' => [
            static fn (Connection $connection) => new StreamDoctrineDbalStore(
                $connection,
                DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            ),
        ];

        yield 'taggable' => [
            static fn (Connection $connection) => new TaggableDoctrineDbalStore(
                $connection,
                DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
                (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            ),
        ];
    }

    /** @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory */
    #[DataProvider('storeProvider')]
    public function testFirstWaitReturnsImmediately(Closure $factory): void
    {
        [$listener] = $this->createStores($factory);

        self::assertLessThan(1000, $this->measure(static fn () => $listener->wait(5000)));
    }

    /** @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory */
    #[DataProvider('storeProvider')]
    public function testWaitReturnsOnSavedEvents(Closure $factory): void
    {
        [$listener, $writer] = $this->createStores($factory);

        $listener->wait(0);

        $writer->save($this->message(1));

        self::assertLessThan(2000, $this->measure(static fn () => $listener->wait(5000)));
    }

    /** @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory */
    #[DataProvider('storeProvider')]
    public function testWaitTimesOutWithoutNewEvents(Closure $factory): void
    {
        [$listener] = $this->createStores($factory);

        $listener->wait(0);

        self::assertGreaterThanOrEqual(250, $this->measure(static fn () => $listener->wait(300)));
    }

    /** @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory */
    #[DataProvider('storeProvider')]
    public function testWaitDiscardsQueuedNotifications(Closure $factory): void
    {
        [$listener, $writer] = $this->createStores($factory);

        $listener->wait(0);

        $writer->save($this->message(1));
        $writer->save($this->message(2));

        // give both notifications time to arrive, so they are queued on the listener connection
        usleep(100_000);

        self::assertLessThan(2000, $this->measure(static fn () => $listener->wait(5000)));
        self::assertGreaterThanOrEqual(250, $this->measure(static fn () => $listener->wait(300)));
    }

    /** @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory */
    #[DataProvider('storeProvider')]
    public function testNoNotificationOnRollback(Closure $factory): void
    {
        [$listener, $writer] = $this->createStores($factory);

        $listener->wait(0);

        try {
            $writer->transactional(function () use ($writer): void {
                $writer->save($this->message(1));

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertGreaterThanOrEqual(250, $this->measure(static fn () => $listener->wait(300)));
    }

    public function testWaitReturnsOnAppendedEvents(): void
    {
        $eventSerializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']);
        $eventRegistry = (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']);

        $listener = new TaggableDoctrineDbalStore($this->listenerConnection, $eventSerializer, $eventRegistry);
        $writer = new TaggableDoctrineDbalStore($this->writerConnection, $eventSerializer, $eventRegistry);

        (new DoctrineSchemaDirector($this->writerConnection, $writer))->create();

        $listener->wait(0);

        $writer->append([$this->message(1)]);

        self::assertLessThan(2000, $this->measure(static fn () => $listener->wait(5000)));
    }

    /**
     * @param Closure(Connection): (Store&ListenableStore&DoctrineSchemaConfigurator) $factory
     *
     * @return array{Store&ListenableStore&DoctrineSchemaConfigurator, Store&ListenableStore&DoctrineSchemaConfigurator}
     */
    private function createStores(Closure $factory): array
    {
        $listener = $factory($this->listenerConnection);
        $writer = $factory($this->writerConnection);

        (new DoctrineSchemaDirector($this->writerConnection, $writer))->create();

        return [$listener, $writer];
    }

    /** @param positive-int $playhead */
    private function message(int $playhead): Message
    {
        return Message::create(new ProfileCreated($this->profileId, 'test'))
            ->withHeader(new StreamNameHeader(sprintf('profile-%s', $this->profileId->toString())))
            ->withHeader(new PlayheadHeader($playhead));
    }

    /**
     * @param Closure():void $callback
     *
     * @return float milliseconds
     */
    private function measure(Closure $callback): float
    {
        $start = hrtime(true);
        $callback();

        return (hrtime(true) - $start) / 1_000_000;
    }
}
