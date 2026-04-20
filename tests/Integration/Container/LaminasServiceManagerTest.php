<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileWithCommands;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjector;
use Patchlevel\EventSourcing\Tests\Integration\Container\Fixture\ProfileRegistration;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_values;

#[CoversNothing]
final class LaminasServiceManagerTest extends TestCase
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

    public function testIntegration(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01 12:00:00'));

        $container = new ServiceManager([
            'services' => [
                Connection::class => $this->connection,
                ClockInterface::class => $clock,
                LoggerInterface::class => new NullLogger(),
            ],
            'abstract_factories' => [ReflectionBasedAbstractFactory::class],
        ]);

        $eventSourcing = Factory::create(
            [
                'connection' => ['service' => Connection::class],
                'aggregates' => [__DIR__ . '/../BasicImplementation'],
                'events' => [__DIR__ . '/../BasicImplementation/Events'],
                'headers' => [__DIR__ . '/../BasicImplementation/Header'],
                'clock' => ['service' => ClockInterface::class],
                'logger' => ['service' => LoggerInterface::class],
                'subscription' => [
                    'subscribers' => [ProfileProjector::class],
                    'sync' => ['throw_on_error' => true],
                ],
                'parameters' => ['env' => 'test'],
            ],
            $container,
        );

        Factory::registerBridges(
            $eventSourcing,
            static function (string $id, callable $service) use ($container): void {
                $container->setFactory($id, static fn (): object => $service());
            },
        );

        $schemaDirector = $container->get(SchemaDirector::class);
        $subscriptionEngine = $container->get(SubscriptionEngine::class);

        $schemaDirector->create();
        $subscriptionEngine->execute(new Setup(skipBooting: true));

        $profileId = ProfileId::generate();
        $registration = $container->get(ProfileRegistration::class);

        self::assertSame('John', $registration->register($profileId, 'John'));

        self::assertSame($clock, $container->get(ClockInterface::class));
        self::assertSame($eventSourcing->get(RepositoryManager::class), $container->get(RepositoryManager::class));

        $profile = $eventSourcing->get(RepositoryManager::class)->get(ProfileWithCommands::class)->load($profileId);

        self::assertSame('John', $profile->name());

        $messages = array_values($eventSourcing->get(Store::class)->load()->toArray());

        self::assertCount(1, $messages);
        self::assertEquals(
            new RecordedOnHeader(new DateTimeImmutable('2026-01-01 12:00:00')),
            $messages[0]->header(RecordedOnHeader::class),
        );
    }
}
