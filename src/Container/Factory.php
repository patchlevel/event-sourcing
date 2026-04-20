<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\ChainHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider as CommandServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand;
use Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaDropCommand;
use Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionBootCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionPauseCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionReactivateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRefreshCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Projection\ProjectionBuilder;
use Patchlevel\EventSourcing\Projection\StoreProjectionBuilder;
use Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider as QueryChainHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\HandlerProvider as QueryHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\QueryBus\ServiceHandlerProvider as QueryServiceHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\EventTagDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaListener;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterRepository;
use Patchlevel\EventSourcing\Snapshot\ArrayAdapterRepository;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatformMiddleware;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RemoveSubscriptionStreamListener;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventEmitterResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\LookupResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_filter;
use function array_map;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Creates a PSR-11 container with all event sourcing services out of an array configuration.
 *
 * @phpstan-import-type Config from Configuration
 * @phpstan-import-type NormalizedConfig from Configuration
 * @phpstan-import-type StoreOptions from Configuration
 */
final class Factory
{
    public const CONNECTION_ID = 'event_sourcing.dbal_connection';
    public const PUBLIC_CONNECTION_ID = 'event_sourcing.dbal_public_connection';

    public const NEW_STORE_ID = 'event_sourcing.store.new_store';

    public const SUBSCRIPTION_EVENT_DISPATCHER_ID = 'event_sourcing.subscription.event_dispatcher';
    public const SUBSCRIPTION_SYNC_ENGINE_ID = 'event_sourcing.subscription.sync_engine';

    public const MIGRATION_DEPENDENCY_FACTORY_ID = 'event_sourcing.migration.dependency_factory';
    public const MIGRATION_DIFF_COMMAND_ID = 'event_sourcing.command.migration_diff';
    public const MIGRATION_MIGRATE_COMMAND_ID = 'event_sourcing.command.migration_migrate';
    public const MIGRATION_CURRENT_COMMAND_ID = 'event_sourcing.command.migration_current';
    public const MIGRATION_EXECUTE_COMMAND_ID = 'event_sourcing.command.migration_execute';
    public const MIGRATION_STATUS_COMMAND_ID = 'event_sourcing.command.migration_status';

    public const PUBLIC_SERVICE_IDS = [
        RepositoryManager::class,
        CommandBus::class,
        QueryBus::class,
        EventBus::class,
        Store::class,
        SnapshotStore::class,
        SubscriptionEngine::class,
        SubscriptionStore::class,
        DecisionModelBuilder::class,
        EventAppender::class,
        ProjectionBuilder::class,
        SchemaDirector::class,
        DoctrineSchemaListener::class,
        Connection::class,
        ClockInterface::class,
        EventSerializer::class,
        HeadersSerializer::class,
        EventTagExtractor::class,
        Hydrator::class,
        AggregateRootRegistry::class,
        EventRegistry::class,
        CipherKeyStore::class,
        Cryptographer::class,
    ];

    public const COMMAND_SERVICE_IDS = [
        ShowCommand::class,
        ShowAggregateCommand::class,
        WatchCommand::class,
        DebugCommand::class,
        DatabaseCreateCommand::class,
        DatabaseDropCommand::class,
        SchemaCreateCommand::class,
        SchemaUpdateCommand::class,
        SchemaDropCommand::class,
        SubscriptionSetupCommand::class,
        SubscriptionBootCommand::class,
        SubscriptionRunCommand::class,
        SubscriptionTeardownCommand::class,
        SubscriptionRemoveCommand::class,
        SubscriptionStatusCommand::class,
        SubscriptionPauseCommand::class,
        SubscriptionReactivateCommand::class,
        SubscriptionRefreshCommand::class,
        StoreMigrateCommand::class,
        self::MIGRATION_DIFF_COMMAND_ID,
        self::MIGRATION_MIGRATE_COMMAND_ID,
        self::MIGRATION_CURRENT_COMMAND_ID,
        self::MIGRATION_EXECUTE_COMMAND_ID,
        self::MIGRATION_STATUS_COMMAND_ID,
    ];

    /**
     * @param Config $config
     *
     * @throws InvalidConfiguration
     */
    public static function create(array $config, ContainerInterface|null $externalContainer = null): Container
    {
        $config = Configuration::normalize($config);
        $container = new Container(externalContainer: $externalContainer);

        self::configureClock($config, $container);
        self::configureConnection($config, $container);
        self::configureLogger($config, $container);
        self::configureHydrator($config, $container);
        self::configureSerializer($config, $container);
        self::configureMessageDecorator($config, $container);
        self::configureCommandBus($config, $container);
        self::configureEventBus($config, $container);
        self::configureQueryBus($config, $container);
        self::configureStore($config, $container);
        self::configureDecisionModel($config, $container);
        self::configureSnapshots($config, $container);
        self::configureAggregates($config, $container);
        self::configureCommands($container);
        self::configureSchema($container);
        self::configureMessageLoader($config, $container);
        self::configureSubscription($config, $container);
        self::configureMigration($config, $container);
        self::configureStoreMigration($config, $container);

        // user provided services are bound last, so they replace the default services with the same id
        foreach ($config['services'] as $id => $service) {
            $container->bind($id, $service);
        }

        foreach ($config['parameters'] as $id => $parameter) {
            $container->bind($id, static fn () => $parameter);
        }

        return $container;
    }

    /**
     * Registers the public event sourcing services into an application container.
     *
     * The given callable is invoked once per available public service with the
     * service id and a lazy factory, so any PSR-11 implementation can map them
     * to its own definition format.
     *
     * @param callable(string, callable(): object): void $register
     */
    public static function registerBridges(Container $container, callable $register): void
    {
        foreach (self::PUBLIC_SERVICE_IDS as $id) {
            // services from the external container are already known by the application container
            if (!$container->provides($id)) {
                continue;
            }

            $register($id, static fn (): object => $container->get($id));
        }
    }

    /** @return list<Command> */
    public static function commands(Container $container): array
    {
        $commands = [];

        foreach (self::COMMAND_SERVICE_IDS as $id) {
            if (!$container->provides($id)) {
                continue;
            }

            $commands[] = self::resolveService($container, $id, Command::class);
        }

        return $commands;
    }

    /** @param NormalizedConfig $config */
    private static function configureClock(array $config, Container $container): void
    {
        $container->bind(SystemClock::class, new SystemClock());
        $container->alias(ClockInterface::class, SystemClock::class);

        if ($config['clock']['service'] !== null) {
            $container->alias(ClockInterface::class, $config['clock']['service']);

            return;
        }

        if ($config['clock']['freeze'] === null) {
            return;
        }

        $container->bind(FrozenClock::class, new FrozenClock(new DateTimeImmutable($config['clock']['freeze'])));
        $container->alias(ClockInterface::class, FrozenClock::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureConnection(array $config, Container $container): void
    {
        $url = $config['connection']['url'];

        if ($url === null) {
            /** @var string $service validated by the configuration */
            $service = $config['connection']['service'];

            $container->alias(self::CONNECTION_ID, $service);
            $container->alias(Connection::class, $service);

            return;
        }

        $factory = new class {
            /**
             * Mapping was taken from Doctrine Bundle.
             *
             * @see https://github.com/doctrine/DoctrineBundle/blob/7564fa72ab4a87316660347ccd226cefc8fb0ea9/src/ConnectionFactory.php#L35
             */
            private const DEFAULT_SCHEME_MAP = [
                'db2' => 'ibm_db2',
                'mssql' => 'pdo_sqlsrv',
                'mysql' => 'pdo_mysql',
                'mysql2' => 'pdo_mysql', // Amazon RDS, for some weird reason
                'postgres' => 'pdo_pgsql',
                'postgresql' => 'pdo_pgsql',
                'pgsql' => 'pdo_pgsql',
                'sqlite' => 'pdo_sqlite',
                'sqlite3' => 'pdo_sqlite',
            ];

            /** @param list<Middleware> $middlewares */
            public static function createConnection(string $url, array $middlewares): Connection
            {
                return DriverManager::getConnection(
                    (new DsnParser(self::DEFAULT_SCHEME_MAP))->parse($url),
                    (new DbalConfiguration())->setMiddlewares($middlewares),
                );
            }
        };

        // the taggable store needs the platform middleware to create a gin index for the tags on postgres
        $middlewares = self::usesTaggableStore($config) ? [new PostgreSQLPlatformMiddleware()] : [];

        $container->bind(
            self::CONNECTION_ID,
            static fn (): Connection => $factory::createConnection($url, $middlewares),
        );

        if (!$config['connection']['provide_dedicated_connection']) {
            $container->alias(Connection::class, self::CONNECTION_ID);

            return;
        }

        $container->bind(self::PUBLIC_CONNECTION_ID, static fn (): Connection => $factory::createConnection($url, []));
        $container->alias(Connection::class, self::PUBLIC_CONNECTION_ID);
    }

    /** @param NormalizedConfig $config */
    private static function usesTaggableStore(array $config): bool
    {
        return $config['store']['type'] === Configuration::STORE_DBAL_TAGGABLE
            || (
                $config['store']['migrate_to_new_store']['enabled']
                && $config['store']['migrate_to_new_store']['type'] === Configuration::STORE_DBAL_TAGGABLE
            );
    }

    /** @param NormalizedConfig $config */
    private static function configureLogger(array $config, Container $container): void
    {
        if ($config['logger']['service'] === null) {
            return;
        }

        $container->alias(LoggerInterface::class, $config['logger']['service']);
    }

    /** @param NormalizedConfig $config */
    private static function configureHydrator(array $config, Container $container): void
    {
        $hydrator = $config['hydrator'];

        if ($hydrator['cryptography']['enabled']) {
            if ($hydrator['cryptography']['cipher_key_store'] !== null) {
                $container->alias(CipherKeyStore::class, $hydrator['cryptography']['cipher_key_store']);
            } else {
                $container->bind(
                    DoctrineCipherKeyStore::class,
                    static fn (Container $container): DoctrineCipherKeyStore => new DoctrineCipherKeyStore(
                        self::resolveService($container, self::CONNECTION_ID, Connection::class),
                    ),
                );
                $container->alias(CipherKeyStore::class, DoctrineCipherKeyStore::class);
            }

            $container->bind(
                BaseCryptographer::class,
                static fn (Container $container): BaseCryptographer => BaseCryptographer::createWithOpenssl(
                    self::resolveService($container, CipherKeyStore::class, CipherKeyStore::class),
                    $hydrator['cryptography']['algorithm'],
                ),
            );
            $container->alias(Cryptographer::class, BaseCryptographer::class);
        }

        $container->bind(
            StackHydrator::class,
            static function (Container $container) use ($hydrator): StackHydrator {
                $builder = new StackHydratorBuilder();

                if ($hydrator['cryptography']['enabled']) {
                    $builder->useExtension(new CryptographyExtension(
                        self::resolveService($container, Cryptographer::class, Cryptographer::class),
                    ));
                }

                $beforeEncoding = self::resolveServices($container, $hydrator['upcasters']['before_encoding'], Upcaster::class);
                $beforeTransform = self::resolveServices($container, $hydrator['upcasters']['before_transform'], Upcaster::class);

                if ($beforeEncoding !== [] || $beforeTransform !== []) {
                    $builder->useExtension(new UpcastExtension($beforeEncoding, $beforeTransform));
                }

                foreach (self::resolveServices($container, $hydrator['extensions'], Extension::class) as $extension) {
                    $builder->useExtension($extension);
                }

                foreach (self::resolveServices($container, $hydrator['guessers'], Guesser::class) as $guesser) {
                    $builder->addGuesser($guesser);
                }

                $builder->useExtension(new CoreExtension());

                if ($hydrator['lifecycle']['enabled']) {
                    $builder->useExtension(new LifecycleExtension());
                }

                $builder->enableDefaultLazy($hydrator['default_lazy']);

                return $builder->build();
            },
        );
        $container->alias(Hydrator::class, StackHydrator::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureSerializer(array $config, Container $container): void
    {
        $container->bind(
            EventRegistry::class,
            static fn (): EventRegistry => (new AttributeEventRegistryFactory())->create($config['events']),
        );

        $container->bind(AttributeEventMetadataFactory::class, new AttributeEventMetadataFactory());
        $container->alias(EventMetadataFactory::class, AttributeEventMetadataFactory::class);

        $container->bind(JsonEncoder::class, new JsonEncoder());
        $container->alias(Encoder::class, JsonEncoder::class);

        $container->bind(
            DefaultEventSerializer::class,
            static fn (Container $container): DefaultEventSerializer => new DefaultEventSerializer(
                $container->get(EventRegistry::class),
                $container->get(Hydrator::class),
                $container->get(Encoder::class),
            ),
        );
        $container->alias(EventSerializer::class, DefaultEventSerializer::class);

        $container->bind(AttributeEventTagExtractor::class, new AttributeEventTagExtractor());
        $container->alias(EventTagExtractor::class, AttributeEventTagExtractor::class);

        $container->bind(AttributeMessageHeaderRegistryFactory::class, new AttributeMessageHeaderRegistryFactory());
        $container->alias(MessageHeaderRegistryFactory::class, AttributeMessageHeaderRegistryFactory::class);

        $container->bind(
            MessageHeaderRegistry::class,
            static fn (Container $container): MessageHeaderRegistry => $container
                ->get(MessageHeaderRegistryFactory::class)
                ->create($config['headers']),
        );

        $container->bind(
            DefaultHeadersSerializer::class,
            static fn (Container $container): DefaultHeadersSerializer => new DefaultHeadersSerializer(
                $container->get(MessageHeaderRegistry::class),
                $container->get(Hydrator::class),
                $container->get(Encoder::class),
            ),
        );
        $container->alias(HeadersSerializer::class, DefaultHeadersSerializer::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureMessageDecorator(array $config, Container $container): void
    {
        $container->bind(
            SplitStreamDecorator::class,
            static fn (Container $container): SplitStreamDecorator => new SplitStreamDecorator(
                $container->get(EventMetadataFactory::class),
            ),
        );

        // events saved by aggregates need tags to be found by the dynamic consistency boundary
        $tagEvents = $config['store']['type'] === Configuration::STORE_DBAL_TAGGABLE || $config['dcb']['enabled'];

        $container->bind(
            ChainMessageDecorator::class,
            static fn (Container $container): ChainMessageDecorator => new ChainMessageDecorator([
                $container->get(SplitStreamDecorator::class),
                ...$tagEvents ?
            [new EventTagDecorator($container->get(EventTagExtractor::class))] :
            [],
                ...self::resolveServices($container, $config['message_decorators'], MessageDecorator::class),
            ]),
        );
        $container->alias(MessageDecorator::class, ChainMessageDecorator::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureCommandBus(array $config, Container $container): void
    {
        $commandBus = $config['command_bus'];

        if (!$commandBus['enabled']) {
            return;
        }

        $container->bind(
            ChainHandlerProvider::class,
            static fn (Container $container): ChainHandlerProvider => new ChainHandlerProvider([
                ...$commandBus['register_aggregate_handlers'] ?
                [
                    new AggregateHandlerProvider(
                        $container->get(AggregateRootRegistry::class),
                        $container->get(RepositoryManager::class),
                        $container,
                    ),
                ] :
            [],
                new CommandServiceHandlerProvider(self::resolveObjects($container, $commandBus['handlers'])),
                ...self::resolveServices($container, $commandBus['handler_providers'], HandlerProvider::class),
            ]),
        );
        $container->alias(HandlerProvider::class, ChainHandlerProvider::class);

        $container->bind(
            SyncCommandBus::class,
            static fn (Container $container): SyncCommandBus => new SyncCommandBus(
                $container->get(HandlerProvider::class),
            ),
        );
        $container->alias(CommandBus::class, SyncCommandBus::class);

        if (!$commandBus['instant_retry']['enabled']) {
            return;
        }

        $container->decorate(
            CommandBus::class,
            InstantRetryCommandBus::class,
            static fn (Container $container, CommandBus $inner): InstantRetryCommandBus => new InstantRetryCommandBus(
                $inner,
                $commandBus['instant_retry']['default_max_retries'],
                $commandBus['instant_retry']['default_exceptions'],
            ),
        );
    }

    /** @param NormalizedConfig $config */
    private static function configureEventBus(array $config, Container $container): void
    {
        $eventBus = $config['event_bus'];

        if (!$eventBus['enabled']) {
            return;
        }

        if ($eventBus['type'] === Configuration::EVENT_BUS_DEFAULT) {
            $container->bind(
                AttributeListenerProvider::class,
                static fn (Container $container): AttributeListenerProvider => new AttributeListenerProvider(
                    self::resolveObjects($container, $eventBus['listeners']),
                ),
            );
            $container->alias(ListenerProvider::class, AttributeListenerProvider::class);

            $container->bind(
                DefaultConsumer::class,
                static fn (Container $container): DefaultConsumer => new DefaultConsumer(
                    $container->get(ListenerProvider::class),
                    self::logger($container),
                ),
            );
            $container->alias(Consumer::class, DefaultConsumer::class);

            $container->bind(
                DefaultEventBus::class,
                static fn (Container $container): DefaultEventBus => new DefaultEventBus(
                    $container->get(Consumer::class),
                    self::logger($container),
                ),
            );
            $container->alias(EventBus::class, DefaultEventBus::class);

            return;
        }

        /** @var string $service validated by the configuration */
        $service = $eventBus['service'];

        if ($eventBus['type'] === Configuration::EVENT_BUS_PSR14) {
            $container->bind(
                Psr14EventBus::class,
                static fn (Container $container): Psr14EventBus => new Psr14EventBus(
                    self::resolveService($container, $service, PsrEventDispatcherInterface::class),
                ),
            );
            $container->alias(EventBus::class, Psr14EventBus::class);

            return;
        }

        $container->alias(EventBus::class, $service);
    }

    /** @param NormalizedConfig $config */
    private static function configureQueryBus(array $config, Container $container): void
    {
        $queryBus = $config['query_bus'];

        if (!$queryBus['enabled']) {
            return;
        }

        $container->bind(
            QueryChainHandlerProvider::class,
            static fn (Container $container): QueryChainHandlerProvider => new QueryChainHandlerProvider([
                // subscribers can answer queries, e.g. a projector which holds the read model
                new QueryServiceHandlerProvider([
                    ...self::resolveObjects($container, $config['subscription']['subscribers']),
                    ...self::resolveObjects($container, $queryBus['handlers']),
                ]),
                ...self::resolveServices($container, $queryBus['handler_providers'], QueryHandlerProvider::class),
            ]),
        );
        $container->alias(QueryHandlerProvider::class, QueryChainHandlerProvider::class);

        $container->bind(
            SyncQueryBus::class,
            static fn (Container $container): SyncQueryBus => new SyncQueryBus(
                $container->get(QueryHandlerProvider::class),
            ),
        );
        $container->alias(QueryBus::class, SyncQueryBus::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureStore(array $config, Container $container): void
    {
        $store = $config['store'];

        if ($store['type'] === Configuration::STORE_CUSTOM) {
            /** @var string $service validated by the configuration */
            $service = $store['service'];

            $container->alias(Store::class, $service);

            return;
        }

        $class = self::bindStore($container, $store['type'], $store['options']);
        $container->alias(Store::class, $class);

        if (!$store['read_only']) {
            return;
        }

        $container->decorate(
            Store::class,
            ReadOnlyStore::class,
            static fn (Container $container, Store $inner): ReadOnlyStore => new ReadOnlyStore(
                $inner,
                self::logger($container),
            ),
        );
    }

    /**
     * Binds a store and returns its id.
     *
     * @param StoreOptions $options
     */
    private static function bindStore(Container $container, string $type, array $options, string|null $id = null): string
    {
        if ($type === Configuration::STORE_IN_MEMORY) {
            $id ??= InMemoryStore::class;

            $container->bind(
                $id,
                static fn (Container $container): InMemoryStore => new InMemoryStore(
                    [],
                    $container->get(EventRegistry::class),
                    $container->get(ClockInterface::class),
                ),
            );

            return $id;
        }

        if ($type === Configuration::STORE_DBAL_TAGGABLE) {
            $id ??= TaggableDoctrineDbalStore::class;

            $container->bind(
                $id,
                static fn (Container $container): TaggableDoctrineDbalStore => new TaggableDoctrineDbalStore(
                    self::resolveService($container, self::CONNECTION_ID, Connection::class),
                    $container->get(EventSerializer::class),
                    $container->get(EventRegistry::class),
                    $container->get(HeadersSerializer::class),
                    $container->get(ClockInterface::class),
                    $options,
                ),
            );

            return $id;
        }

        $id ??= StreamDoctrineDbalStore::class;

        // the stream store has no default stream, every message has a stream header
        unset($options['default_stream_name']);

        $container->bind(
            $id,
            static fn (Container $container): StreamDoctrineDbalStore => new StreamDoctrineDbalStore(
                self::resolveService($container, self::CONNECTION_ID, Connection::class),
                $container->get(EventSerializer::class),
                $container->get(HeadersSerializer::class),
                $container->get(ClockInterface::class),
                $options,
            ),
        );

        return $id;
    }

    /** @param NormalizedConfig $config */
    private static function configureDecisionModel(array $config, Container $container): void
    {
        if (!$config['dcb']['enabled']) {
            return;
        }

        $container->bind(
            StoreDecisionModelBuilder::class,
            static fn (Container $container): StoreDecisionModelBuilder => new StoreDecisionModelBuilder(
                self::resolveService($container, Store::class, AppendStore::class),
            ),
        );
        $container->alias(DecisionModelBuilder::class, StoreDecisionModelBuilder::class);

        $container->bind(
            StoreEventAppender::class,
            static fn (Container $container): StoreEventAppender => new StoreEventAppender(
                self::resolveService($container, Store::class, AppendStore::class),
                $container->get(EventTagExtractor::class),
                $config['store']['options']['default_stream_name'] ?? 'main',
            ),
        );
        $container->alias(EventAppender::class, StoreEventAppender::class);

        $container->bind(
            StoreProjectionBuilder::class,
            static fn (Container $container): StoreProjectionBuilder => new StoreProjectionBuilder(
                self::resolveService($container, Store::class, AppendStore::class),
            ),
        );
        $container->alias(ProjectionBuilder::class, StoreProjectionBuilder::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureSnapshots(array $config, Container $container): void
    {
        if ($config['snapshot_stores'] === []) {
            return;
        }

        $container->bind(
            AdapterRepository::class,
            static function (Container $container) use ($config): ArrayAdapterRepository {
                $adapters = [];

                foreach ($config['snapshot_stores'] as $name => $definition) {
                    if ($definition instanceof SnapshotAdapter) {
                        $adapters[$name] = $definition;

                        continue;
                    }

                    $adapters[$name] = match ($definition['type']) {
                        'psr6' => new Psr6SnapshotAdapter(
                            self::resolveService($container, $definition['service'], CacheItemPoolInterface::class),
                        ),
                        'psr16' => new Psr16SnapshotAdapter(
                            self::resolveService($container, $definition['service'], CacheInterface::class),
                        ),
                        'custom' => self::resolveService($container, $definition['service'], SnapshotAdapter::class),
                    };
                }

                return new ArrayAdapterRepository($adapters);
            },
        );

        $container->bind(
            DefaultSnapshotStore::class,
            static fn (Container $container): DefaultSnapshotStore => new DefaultSnapshotStore(
                $container->get(AdapterRepository::class),
                $container->get(Hydrator::class),
                $container->get(AggregateRootMetadataFactory::class),
            ),
        );
        $container->alias(SnapshotStore::class, DefaultSnapshotStore::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureAggregates(array $config, Container $container): void
    {
        $container->bind(
            AggregateRootMetadataAwareMetadataFactory::class,
            new AggregateRootMetadataAwareMetadataFactory(),
        );
        $container->alias(AggregateRootMetadataFactory::class, AggregateRootMetadataAwareMetadataFactory::class);

        $container->bind(
            AggregateRootRegistry::class,
            static fn (): AggregateRootRegistry => (new AttributeAggregateRootRegistryFactory())->create(
                $config['aggregates'],
            ),
        );

        $container->bind(
            DefaultRepositoryManager::class,
            static fn (Container $container): DefaultRepositoryManager => new DefaultRepositoryManager(
                $container->get(AggregateRootRegistry::class),
                $container->get(Store::class),
                $container->has(EventBus::class) ? $container->get(EventBus::class) : null,
                $container->has(SnapshotStore::class) ? $container->get(SnapshotStore::class) : null,
                $container->get(MessageDecorator::class),
                $container->get(ClockInterface::class),
                $container->get(AggregateRootMetadataFactory::class),
                self::logger($container),
            ),
        );
        $container->alias(RepositoryManager::class, DefaultRepositoryManager::class);
    }

    private static function configureCommands(Container $container): void
    {
        $container->bind(
            ShowCommand::class,
            static fn (Container $container): ShowCommand => new ShowCommand(
                $container->get(Store::class),
                $container->get(EventSerializer::class),
                $container->get(HeadersSerializer::class),
            ),
        );
        $container->bind(
            ShowAggregateCommand::class,
            static fn (Container $container): ShowAggregateCommand => new ShowAggregateCommand(
                $container->get(Store::class),
                $container->get(EventSerializer::class),
                $container->get(HeadersSerializer::class),
                $container->get(AggregateRootRegistry::class),
                $container->get(AggregateRootMetadataFactory::class),
            ),
        );
        $container->bind(
            WatchCommand::class,
            static fn (Container $container): WatchCommand => new WatchCommand(
                $container->get(Store::class),
                $container->get(EventSerializer::class),
                $container->get(HeadersSerializer::class),
            ),
        );
        $container->bind(
            DebugCommand::class,
            static fn (Container $container): DebugCommand => new DebugCommand(
                $container->get(AggregateRootRegistry::class),
                $container->get(EventRegistry::class),
                $container->get(SubscriberAccessorRepository::class),
            ),
        );
        $container->bind(
            SubscriptionSetupCommand::class,
            static fn (Container $container): SubscriptionSetupCommand => new SubscriptionSetupCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionBootCommand::class,
            static fn (Container $container): SubscriptionBootCommand => new SubscriptionBootCommand(
                $container->get(SubscriptionEngine::class),
                self::eventDispatcher($container),
            ),
        );
        $container->bind(
            SubscriptionRunCommand::class,
            static fn (Container $container): SubscriptionRunCommand => new SubscriptionRunCommand(
                $container->get(SubscriptionEngine::class),
                $container->get(Store::class),
                self::eventDispatcher($container),
            ),
        );
        $container->bind(
            SubscriptionTeardownCommand::class,
            static fn (Container $container): SubscriptionTeardownCommand => new SubscriptionTeardownCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionRemoveCommand::class,
            static fn (Container $container): SubscriptionRemoveCommand => new SubscriptionRemoveCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionStatusCommand::class,
            static fn (Container $container): SubscriptionStatusCommand => new SubscriptionStatusCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionPauseCommand::class,
            static fn (Container $container): SubscriptionPauseCommand => new SubscriptionPauseCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionReactivateCommand::class,
            static fn (Container $container): SubscriptionReactivateCommand => new SubscriptionReactivateCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
        $container->bind(
            SubscriptionRefreshCommand::class,
            static fn (Container $container): SubscriptionRefreshCommand => new SubscriptionRefreshCommand(
                $container->get(SubscriptionEngine::class),
            ),
        );
    }

    private static function configureSchema(Container $container): void
    {
        $container->bind(
            ChainDoctrineSchemaConfigurator::class,
            static function (Container $container): ChainDoctrineSchemaConfigurator {
                $services = [];

                foreach ([Store::class, SubscriptionStore::class, self::NEW_STORE_ID, CipherKeyStore::class] as $id) {
                    if (!$container->has($id)) {
                        continue;
                    }

                    $services[] = $container->get($id);
                }

                return new ChainDoctrineSchemaConfigurator(array_filter(
                    $services,
                    static fn (object $service): bool => $service instanceof DoctrineSchemaConfigurator,
                ));
            },
        );
        $container->alias(DoctrineSchemaConfigurator::class, ChainDoctrineSchemaConfigurator::class);

        $container->bind(
            DoctrineSchemaListener::class,
            static fn (Container $container): DoctrineSchemaListener => new DoctrineSchemaListener(
                $container->get(DoctrineSchemaConfigurator::class),
            ),
        );

        $container->bind(
            DoctrineSchemaDirector::class,
            static fn (Container $container): DoctrineSchemaDirector => new DoctrineSchemaDirector(
                self::resolveService($container, self::CONNECTION_ID, Connection::class),
                $container->get(DoctrineSchemaConfigurator::class),
            ),
        );
        $container->alias(DoctrineSchemaProvider::class, DoctrineSchemaDirector::class);
        $container->alias(SchemaDirector::class, DoctrineSchemaDirector::class);

        $container->bind(DoctrineHelper::class, new DoctrineHelper());

        $container->bind(
            DatabaseCreateCommand::class,
            static fn (Container $container): DatabaseCreateCommand => new DatabaseCreateCommand(
                self::resolveService($container, self::CONNECTION_ID, Connection::class),
                $container->get(DoctrineHelper::class),
            ),
        );
        $container->bind(
            DatabaseDropCommand::class,
            static fn (Container $container): DatabaseDropCommand => new DatabaseDropCommand(
                self::resolveService($container, self::CONNECTION_ID, Connection::class),
                $container->get(DoctrineHelper::class),
            ),
        );
        $container->bind(
            SchemaCreateCommand::class,
            static fn (Container $container): SchemaCreateCommand => new SchemaCreateCommand(
                $container->get(SchemaDirector::class),
            ),
        );
        $container->bind(
            SchemaUpdateCommand::class,
            static fn (Container $container): SchemaUpdateCommand => new SchemaUpdateCommand(
                $container->get(SchemaDirector::class),
            ),
        );
        $container->bind(
            SchemaDropCommand::class,
            static fn (Container $container): SchemaDropCommand => new SchemaDropCommand(
                $container->get(SchemaDirector::class),
            ),
        );
    }

    /** @param NormalizedConfig $config */
    private static function configureMessageLoader(array $config, Container $container): void
    {
        $container->bind(
            StoreMessageLoader::class,
            static fn (Container $container): StoreMessageLoader => new StoreMessageLoader(
                $container->get(Store::class),
            ),
        );
        $container->alias(MessageLoader::class, StoreMessageLoader::class);

        if ($config['subscription']['event_filtered_message_loader']['enabled']) {
            $container->bind(
                EventFilteredStoreMessageLoader::class,
                static fn (Container $container): EventFilteredStoreMessageLoader => new EventFilteredStoreMessageLoader(
                    $container->get(Store::class),
                    $container->get(EventMetadataFactory::class),
                    $container->get(SubscriberAccessorRepository::class),
                ),
            );
            $container->alias(MessageLoader::class, EventFilteredStoreMessageLoader::class);

            return;
        }

        $gapDetection = $config['subscription']['gap_detection'];

        if (!$gapDetection['enabled']) {
            return;
        }

        $container->bind(
            GapResolverStoreMessageLoader::class,
            static fn (Container $container): GapResolverStoreMessageLoader => new GapResolverStoreMessageLoader(
                $container->get(Store::class),
                $container->get(ClockInterface::class),
                $gapDetection['retries_in_ms'],
                $gapDetection['detection_window'] !== null ? new DateInterval($gapDetection['detection_window']) : null,
            ),
        );
        $container->alias(MessageLoader::class, GapResolverStoreMessageLoader::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureSubscription(array $config, Container $container): void
    {
        $subscription = $config['subscription'];

        $container->bind(AttributeSubscriberMetadataFactory::class, new AttributeSubscriberMetadataFactory());
        $container->alias(SubscriberMetadataFactory::class, AttributeSubscriberMetadataFactory::class);

        $container->bind(
            RetryStrategyRepository::class,
            static function (Container $container) use ($subscription): RetryStrategyRepository {
                $strategies = [];

                foreach ($subscription['retry_strategies'] as $name => $definition) {
                    $strategies[$name] = match ($definition['type']) {
                        Configuration::SUBSCRIPTION_RETRY_CLOCK_BASED => new ClockBasedRetryStrategy(
                            $container->get(ClockInterface::class),
                            $definition['options']['base_delay'] ?? ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                            $definition['options']['delay_factor'] ?? ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                            $definition['options']['max_attempts'] ?? ClockBasedRetryStrategy::DEFAULT_MAX_ATTEMPTS,
                        ),
                        Configuration::SUBSCRIPTION_RETRY_NO_RETRY => new NoRetryStrategy(),
                        Configuration::SUBSCRIPTION_RETRY_CUSTOM => self::resolveService(
                            $container,
                            (string)$definition['service'],
                            RetryStrategy::class,
                        ),
                    };
                }

                return new RetryStrategyRepository($strategies, $subscription['default_retry_strategy']);
            },
        );

        self::configureSubscriptionStore($config, $container);

        $container->bind(
            LookupResolver::class,
            static fn (Container $container): LookupResolver => new LookupResolver(
                $container->get(Store::class),
                $container->get(EventRegistry::class),
            ),
        );

        $container->bind(
            MetadataSubscriberAccessorRepository::class,
            static fn (Container $container): MetadataSubscriberAccessorRepository => new MetadataSubscriberAccessorRepository(
                self::resolveObjects($container, $subscription['subscribers']),
                $container->get(SubscriberMetadataFactory::class),
            ),
        );
        $container->alias(SubscriberAccessorRepository::class, MetadataSubscriberAccessorRepository::class);

        $container->bind(
            DefaultCleaner::class,
            static fn (Container $container): DefaultCleaner => new DefaultCleaner([
                new DbalCleanupTaskHandler(self::resolveService($container, self::CONNECTION_ID, Connection::class)),
                ...self::resolveServices($container, $subscription['cleanup_task_handlers'], CleanupTaskHandler::class),
            ]),
        );
        $container->alias(Cleaner::class, DefaultCleaner::class);

        $eventEmitter = $subscription['event_emitter']['enabled'];

        $container->bind(
            self::SUBSCRIPTION_EVENT_DISPATCHER_ID,
            static function (Container $container) use ($eventEmitter): EventDispatcher {
                $eventDispatcher = new EventDispatcher();

                if ($eventEmitter) {
                    $eventDispatcher->addListener(
                        OnSubscriptionRemoved::class,
                        new RemoveSubscriptionStreamListener($container->get(Store::class), self::logger($container)),
                    );
                }

                return $eventDispatcher;
            },
        );

        $container->bind(
            DefaultSubscriptionEngine::class,
            static fn (Container $container): DefaultSubscriptionEngine => new DefaultSubscriptionEngine(
                $container->get(MessageLoader::class),
                $container->get(SubscriptionStore::class),
                $container->get(SubscriberAccessorRepository::class),
                $container->get(RetryStrategyRepository::class),
                self::logger($container),
                $container->get(Cleaner::class),
                self::resolveService($container, self::SUBSCRIPTION_EVENT_DISPATCHER_ID, EventDispatcherInterface::class),
                [
                    $container->get(LookupResolver::class),
                    ...$eventEmitter ?
                [new EventEmitterResolver($container->get(Store::class))] :
                [],
                    ...self::resolveServices($container, $subscription['argument_resolvers'], ArgumentResolver::class),
                ],
            ),
        );
        $container->alias(SubscriptionEngine::class, DefaultSubscriptionEngine::class);

        $sync = $subscription['sync'];

        if (!$sync['enabled']) {
            return;
        }

        // the sync engine is only used to run the subscriptions after an aggregate was saved
        $container->bind(
            self::SUBSCRIPTION_SYNC_ENGINE_ID,
            static function (Container $container) use ($sync): SubscriptionEngine {
                $engine = new CatchUpSubscriptionEngine(
                    $container->get(DefaultSubscriptionEngine::class),
                    $sync['catch_up_limit'],
                );

                return $sync['throw_on_error'] ? new ThrowOnErrorSubscriptionEngine($engine) : $engine;
            },
        );

        $container->decorate(
            RepositoryManager::class,
            RunSubscriptionEngineRepositoryManager::class,
            static fn (Container $container, RepositoryManager $inner): RunSubscriptionEngineRepositoryManager => new RunSubscriptionEngineRepositoryManager(
                $inner,
                self::resolveService($container, self::SUBSCRIPTION_SYNC_ENGINE_ID, SubscriptionEngine::class),
                $sync['ids'] ?: null,
                $sync['groups'] ?: null,
            ),
        );
    }

    /** @param NormalizedConfig $config */
    private static function configureSubscriptionStore(array $config, Container $container): void
    {
        $store = $config['subscription']['store'];

        if ($store['type'] === Configuration::SUBSCRIPTION_STORE_CUSTOM) {
            /** @var string $service validated by the configuration */
            $service = $store['service'];

            $container->alias(SubscriptionStore::class, $service);

            return;
        }

        if ($store['type'] === Configuration::SUBSCRIPTION_STORE_DBAL) {
            $container->bind(
                DoctrineSubscriptionStore::class,
                static fn (Container $container): DoctrineSubscriptionStore => new DoctrineSubscriptionStore(
                    self::resolveService($container, self::CONNECTION_ID, Connection::class),
                    $container->get(ClockInterface::class),
                    $store['options']['table_name'],
                ),
            );
            $container->alias(SubscriptionStore::class, DoctrineSubscriptionStore::class);

            return;
        }

        if ($store['type'] === Configuration::SUBSCRIPTION_STORE_IN_MEMORY) {
            $container->bind(
                InMemorySubscriptionStore::class,
                static fn (Container $container): InMemorySubscriptionStore => new InMemorySubscriptionStore(
                    [],
                    $container->get(ClockInterface::class),
                ),
            );
            $container->alias(SubscriptionStore::class, InMemorySubscriptionStore::class);

            return;
        }

        // shared by all containers of the process, meant for tests only
        $factory = new class {
            private static InMemorySubscriptionStore|null $store = null;

            public static function create(): InMemorySubscriptionStore
            {
                return self::$store ??= new InMemorySubscriptionStore();
            }
        };

        $container->bind(InMemorySubscriptionStore::class, static fn (): InMemorySubscriptionStore => $factory::create());
        $container->alias(SubscriptionStore::class, InMemorySubscriptionStore::class);
    }

    /** @param NormalizedConfig $config */
    private static function configureMigration(array $config, Container $container): void
    {
        $migration = $config['migration'];

        if (!$migration['enabled']) {
            return;
        }

        $container->bind(
            DoctrineMigrationSchemaProvider::class,
            static fn (Container $container): DoctrineMigrationSchemaProvider => new DoctrineMigrationSchemaProvider(
                $container->get(DoctrineSchemaProvider::class),
            ),
        );

        $container->bind(
            self::MIGRATION_DEPENDENCY_FACTORY_ID,
            static function (Container $container) use ($migration): DependencyFactory {
                $dependencyFactory = DependencyFactory::fromConnection(
                    new ConfigurationArray([
                        'migrations_paths' => [$migration['namespace'] => $migration['path']],
                    ]),
                    new ExistingConnection(self::resolveService($container, self::CONNECTION_ID, Connection::class)),
                    self::logger($container),
                );

                $dependencyFactory->setService(
                    SchemaProvider::class,
                    $container->get(DoctrineMigrationSchemaProvider::class),
                );

                return $dependencyFactory;
            },
        );

        $commands = [
            self::MIGRATION_DIFF_COMMAND_ID => [DiffCommand::class, 'event-sourcing:migration:diff'],
            self::MIGRATION_MIGRATE_COMMAND_ID => [MigrateCommand::class, 'event-sourcing:migration:migrate'],
            self::MIGRATION_CURRENT_COMMAND_ID => [CurrentCommand::class, 'event-sourcing:migration:current'],
            self::MIGRATION_EXECUTE_COMMAND_ID => [ExecuteCommand::class, 'event-sourcing:migration:execute'],
            self::MIGRATION_STATUS_COMMAND_ID => [StatusCommand::class, 'event-sourcing:migration:status'],
        ];

        foreach ($commands as $id => [$class, $name]) {
            $container->bind(
                $id,
                static fn (Container $container): Command => new $class(
                    self::resolveService($container, self::MIGRATION_DEPENDENCY_FACTORY_ID, DependencyFactory::class),
                    $name,
                ),
            );
        }
    }

    /** @param NormalizedConfig $config */
    private static function configureStoreMigration(array $config, Container $container): void
    {
        $migration = $config['store']['migrate_to_new_store'];

        if (!$migration['enabled']) {
            return;
        }

        if ($migration['type'] === Configuration::STORE_CUSTOM) {
            /** @var string $service validated by the configuration */
            $service = $migration['service'];

            $container->alias(self::NEW_STORE_ID, $service);
        } else {
            self::bindStore($container, $migration['type'], $migration['options'], self::NEW_STORE_ID);
        }

        $container->bind(
            StoreMigrateCommand::class,
            static fn (Container $container): StoreMigrateCommand => new StoreMigrateCommand(
                $container->get(Store::class),
                self::resolveService($container, self::NEW_STORE_ID, Store::class),
                self::resolveServices($container, $migration['translators'], Translator::class),
            ),
        );
    }

    private static function logger(Container $container): LoggerInterface|null
    {
        return $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null;
    }

    private static function eventDispatcher(Container $container): EventDispatcherInterface|null
    {
        return $container->has(EventDispatcherInterface::class) ? $container->get(EventDispatcherInterface::class) : null;
    }

    /**
     * Resolves a list of objects and service ids into objects.
     *
     * @param list<object|string> $services
     *
     * @return list<object>
     */
    private static function resolveObjects(Container $container, array $services): array
    {
        return array_map(
            static fn (object|string $service): object => is_string($service) ? $container->get($service) : $service,
            $services,
        );
    }

    /**
     * Resolves a list of objects and service ids into objects of the given type.
     *
     * @param list<T|string>  $services
     * @param class-string<T> $class
     *
     * @return list<T>
     *
     * @template T of object
     */
    private static function resolveServices(Container $container, array $services, string $class): array
    {
        return array_map(
            static fn (object|string $service): object => is_string($service)
                ? self::resolveService($container, $service, $class)
                : $service,
            $services,
        );
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    private static function resolveService(Container $container, string $id, string $class): object
    {
        $service = $container->get($id);

        if (!$service instanceof $class) {
            throw new ServiceCreationFailed(sprintf(
                'Service "%s" must be an instance of "%s", got "%s".',
                $id,
                $class,
                get_debug_type($service),
            ));
        }

        return $service;
    }
}
