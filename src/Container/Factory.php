<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\ChainHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
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
use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Cryptography\ExtensionDoctrineCipherKeyStore;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
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
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\QueryBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use Patchlevel\EventSourcing\Snapshot\AdapterRepository;
use Patchlevel\EventSourcing\Snapshot\ArrayAdapterRepository;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamReadOnlyStore;
use Patchlevel\EventSourcing\Store\StreamStore;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
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
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\LookupResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_filter;
use function array_key_exists;
use function sprintf;

/**
 * @phpstan-type ServiceMap array<string, object>
 * @phpstan-type AliasMap array<string, string>
 * @phpstan-type FactoryMap array<string, callable(ContainerInterface): object>
 */
final class Factory
{
    public const CONNECTION_ID = 'event_sourcing.dbal_connection';
    public const PUBLIC_CONNECTION_ID = 'event_sourcing.dbal_public_connection';

    public const NEW_STORE_ID = 'event_sourcing.store.new_store';

    public static function create(Configuration $configuration): Container
    {
        $container = new Container();

        foreach ($configuration->services as $id => $service) {
            $container->bind($id, $service);
        }

        foreach ($configuration->parameters as $id => $parameter) {
            $container->bind($id, static fn () => $parameter);
        }

        self::configureClock($configuration, $container);
        self::configureConnection($configuration, $container);
        self::configureHydrator($configuration, $container);
        self::configureUpcaster($configuration, $container);
        self::configureSerializer($configuration, $container);
        self::configureMessageDecorator($configuration, $container);
        self::configureCommandBus($configuration, $container);
        self::configureEventBus($configuration, $container);
        self::configureQueryBus($configuration, $container);
        self::configureLogger($configuration, $container);
        self::configureStore($configuration, $container);
        self::configureSnapshots($configuration, $container);
        self::configureAggregates($configuration, $container);
        self::configureCommands($configuration, $container);
        self::configureSchema($configuration, $container);
        self::configureMessageLoader($configuration, $container);
        self::configureSubscription($configuration, $container);
        // self::configureMigration($configuration, $container); @todo
        self::configureStoreMigration($configuration, $container);

        return $container;
    }

    private static function configureClock(Configuration $configuration, Container $container): void
    {
        $container->bind(SystemClock::class, new SystemClock());
        $container->alias(ClockInterface::class, SystemClock::class);

        if ($configuration->clockService !== null) {
            $container->alias(ClockInterface::class, $configuration->clockService);

            return;
        }

        if ($configuration->frozenClock === null) {
            return;
        }

        $container->bind(FrozenClock::class, new FrozenClock($configuration->frozenClock));
        $container->alias(ClockInterface::class, FrozenClock::class);
    }

    private static function configureHydrator(Configuration $configuration, Container $container): void
    {
        if ($configuration->hydratorStackCryptographyEnabled) {
            if ($configuration->hydratorStackCryptographyCipherKeyStoreService !== null) {
                $container->alias(CipherKeyStore::class, $configuration->hydratorStackCryptographyCipherKeyStoreService);
            } else {
                $container->bind(
                    ExtensionDoctrineCipherKeyStore::class,
                    static function (Container $container): ExtensionDoctrineCipherKeyStore {
                        return new ExtensionDoctrineCipherKeyStore($container->get(self::CONNECTION_ID));
                    },
                );
                $container->alias(CipherKeyStore::class, ExtensionDoctrineCipherKeyStore::class);
            }

            $container->bind(
                BaseCryptographer::class,
                static function (Container $container) use ($configuration): BaseCryptographer {
                    return BaseCryptographer::createWithOpenssl(
                        $container->get(CipherKeyStore::class),
                        $configuration->hydratorStackCryptographyAlgorithm,
                    );
                },
            );
            $container->alias(Cryptographer::class, BaseCryptographer::class);
        }

        $container->bind(
            StackHydrator::class,
            static function (Container $container) use ($configuration): StackHydrator {
                $builder = new StackHydratorBuilder();

                if ($configuration->hydratorStackCryptographyEnabled) {
                    $builder->useExtension(new CryptographyExtension(
                        $container->get(Cryptographer::class),
                        $container->has(PayloadCryptographer::class) ? $container->get(PayloadCryptographer::class) : null,
                        $configuration->hydratorStackCryptographyLegacyMetadataMapping,
                    ));
                }

                foreach ($configuration->hydratorExtensions as $extension) {
                    $builder->useExtension($extension);
                }

                foreach ($configuration->guesser as $guesser) {
                    $builder->addGuesser($guesser);
                }

                $builder->useExtension(new CoreExtension());

                if ($configuration->hydratorLifecycleEnabled) {
                    $builder->useExtension(new LifecycleExtension());
                }

                $builder->enableDefaultLazy($configuration->hydratorDefaultLazy);

                return $builder->build();
            },
        );
        $container->alias(Hydrator::class, StackHydrator::class);
    }

    private static function configureUpcaster(Configuration $configuration, Container $container): void
    {
        $container->bind(
            UpcasterChain::class,
            static fn (): UpcasterChain => new UpcasterChain($configuration->upcasters),
        );
        $container->alias(Upcaster::class, UpcasterChain::class);
    }

    private static function configureSerializer(Configuration $configuration, Container $container): void
    {
        $container->bind(
            EventRegistry::class,
            (new AttributeEventRegistryFactory())->create($configuration->events),
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
                $container->get(Upcaster::class),
            ),
        );
        $container->alias(EventSerializer::class, DefaultEventSerializer::class);

        $container->bind(AttributeMessageHeaderRegistryFactory::class, new AttributeMessageHeaderRegistryFactory());
        $container->alias(MessageHeaderRegistryFactory::class, AttributeMessageHeaderRegistryFactory::class);

        $container->bind(
            MessageHeaderRegistry::class,
            static function (Container $container) use ($configuration): MessageHeaderRegistry {
                return $container->get(MessageHeaderRegistryFactory::class)->create($configuration->headers);
            },
        );

        $container->bind(
            DefaultHeadersSerializer::class,
            static function (Container $container): DefaultHeadersSerializer {
                return new DefaultHeadersSerializer(
                    $container->get(MessageHeaderRegistry::class),
                    $container->get(Hydrator::class),
                    $container->get(Encoder::class),
                );
            },
        );
        $container->alias(HeadersSerializer::class, DefaultHeadersSerializer::class);
    }

    private static function configureMessageDecorator(Configuration $configuration, Container $container): void
    {
        $container->bind(
            SplitStreamDecorator::class,
            static function (Container $container): SplitStreamDecorator {
                return new SplitStreamDecorator($container->get(EventMetadataFactory::class));
            },
        );

        $container->bind(
            ChainMessageDecorator::class,
            static function (Container $container) use ($configuration): ChainMessageDecorator {
                return new ChainMessageDecorator([
                    $container->get(SplitStreamDecorator::class),
                    ...$configuration->messageDecorators,
                ]);
            },
        );
        $container->alias(MessageDecorator::class, ChainMessageDecorator::class);
    }

    private static function configureCommandBus(Configuration $configuration, Container $container): void
    {
        $container->bind(
            ChainHandlerProvider::class,
            static function (Container $container) use ($configuration): ChainHandlerProvider {
                $aggregateHandlerProvider = new AggregateHandlerProvider(
                    $container->get(AggregateRootRegistry::class),
                    $container->get(RepositoryManager::class),
                    $container,
                );

                return new ChainHandlerProvider([
                    $aggregateHandlerProvider,
                    ...$configuration->commandHandlerProviders,
                ]);
            },
        );
        $container->alias(HandlerProvider::class, ChainHandlerProvider::class);

        $container->bind(
            SyncCommandBus::class,
            static fn (Container $container,
            ): SyncCommandBus => new SyncCommandBus($container->get(HandlerProvider::class)),
        );
        $container->alias(CommandBus::class, SyncCommandBus::class);

        if (!$configuration->commandBusInstantRetry) {
            return;
        }

        $container->decorate(
            CommandBus::class,
            InstantRetryCommandBus::class,
            static function (Container $container, CommandBus $inner) use ($configuration): InstantRetryCommandBus {
                return new InstantRetryCommandBus(
                    $inner,
                    $configuration->commandBusInstantRetryDefaultMaxRetries,
                    $configuration->commandBusInstantRetryDefaultExceptions,
                );
            },
        );
    }

    private static function configureEventBus(Configuration $configuration, Container $container): void
    {
        if ($configuration->eventBusEnabled === false) {
            return;
        }

        if ($configuration->eventBusType === Configuration::EVENT_BUS_DEFAULT) {
            $container->bind(
                AttributeListenerProvider::class,
                new AttributeListenerProvider($configuration->listeners),
            );
            $container->alias(ListenerProvider::class, AttributeListenerProvider::class);

            $container->bind(
                DefaultConsumer::class,
                static function (Container $container): DefaultConsumer {
                    return new DefaultConsumer(
                        $container->get(ListenerProvider::class),
                        $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
                    );
                },
            );
            $container->alias(Consumer::class, DefaultConsumer::class);

            $container->bind(
                DefaultEventBus::class,
                static function (Container $container): DefaultEventBus {
                    return new DefaultEventBus(
                        $container->get(Consumer::class),
                        $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
                    );
                },
            );
            $container->alias(EventBus::class, DefaultEventBus::class);

            return;
        }

        if ($configuration->eventBusType === Configuration::EVENT_BUS_PSR14) {
            $container->bind(
                Psr14EventBus::class,
                static function (Container $container): Psr14EventBus {
                    return new Psr14EventBus($container->get(\Psr\EventDispatcher\EventDispatcherInterface::class));
                },
            );
            $container->alias(EventBus::class, Psr14EventBus::class);

            return;
        }

        if ($configuration->eventBusType === Configuration::EVENT_BUS_CUSTOM) {
            if ($configuration->eventBusService === null) {
                throw new InvalidArgumentException('Custom event bus type requires an event bus service id.');
            }

            $container->alias(EventBus::class, $configuration->eventBusService);

            return;
        }

        throw new InvalidArgumentException(sprintf('Unknown event bus type "%s".', $configuration->eventBusType));
    }

    private static function configureQueryBus(Configuration $configuration, Container $container): void
    {
        $container->bind(
            \Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider::class,
            static function (Container $container) use ($configuration,
            ): \Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider {
                $serviceHandlerProvider = new ServiceHandlerProvider($configuration->subscribers);

                return new \Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider([
                    $serviceHandlerProvider,
                    ...$configuration->queryHandlerProviders,
                ]);
            },
        );
        $container->alias(
            \Patchlevel\EventSourcing\QueryBus\HandlerProvider::class,
            \Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider::class,
        );

        $container->bind(
            SyncQueryBus::class,
            static fn (Container $container,
            ) => new SyncQueryBus($container->get(\Patchlevel\EventSourcing\QueryBus\HandlerProvider::class)),
        );
        $container->alias(QueryBus::class, SyncQueryBus::class);
    }

    private static function configureConnection(Configuration $configuration, Container $container): void
    {
        if ($configuration->connectionUrl !== null) {
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

                public static function createConnection(string $url): Connection
                {
                    return DriverManager::getConnection((new DsnParser(self::DEFAULT_SCHEME_MAP))->parse($url));
                }
            };

            $container->bind(
                self::CONNECTION_ID,
                static fn (): Connection => $factory::createConnection($configuration->connectionUrl),
            );

            if ($configuration->dedicatedProjectionConnection) {
                $container->bind(
                    self::PUBLIC_CONNECTION_ID,
                    static fn (): Connection => $factory::createConnection($configuration->connectionUrl),
                );

                $container->alias(Connection::class, self::PUBLIC_CONNECTION_ID);
            }

            return;
        }

        if ($configuration->connectionService !== null) {
            if ($configuration->dedicatedProjectionConnection) {
                throw new InvalidArgumentException('Providing dedicated connection is only possible with url');
            }

            $container->bind(
                self::CONNECTION_ID,
                static function (Container $container) use ($configuration): Connection {
                    return $container->get($configuration->connectionService);
                },
            );
            $container->alias(Connection::class, self::CONNECTION_ID);

            return;
        }

        throw new InvalidArgumentException('Connection service or url is required');
    }

    private static function configureLogger(Configuration $configuration, Container $container): void
    {
        if ($configuration->loggerService === null) {
            return;
        }

        $container->bind(
            LoggerInterface::class,
            static fn (Container $container): LoggerInterface => $container->get($configuration->loggerService),
        );
    }

    private static function configureStore(Configuration $configuration, Container $container): void
    {
        if ($configuration->storeType === $configuration::STORE_CUSTOM) {
            if ($configuration->storeService === null) {
                throw new InvalidArgumentException('Custom store type requires a store service id.');
            }

            $container->alias(Store::class, $configuration->storeService);

            return;
        }

        if ($configuration->storeType === $configuration::STORE_IN_MEMORY) {
            $container->bind(
                InMemoryStore::class,
                static function (Container $container): InMemoryStore {
                    return new InMemoryStore(
                        [],
                        $container->get(EventRegistry::class),
                        $container->get(ClockInterface::class),
                    );
                },
            );
            $container->alias(Store::class, InMemoryStore::class);

            return;
        }

        if ($configuration->storeType === $configuration::STORE_DBAL_STREAM) {
            $container->bind(
                StreamDoctrineDbalStore::class,
                static function (Container $container) use ($configuration): StreamDoctrineDbalStore {
                    return new StreamDoctrineDbalStore(
                        $container->get(self::CONNECTION_ID),
                        $container->get(EventSerializer::class),
                        $container->get(HeadersSerializer::class),
                        $container->get(ClockInterface::class),
                        $configuration->storeOptions,
                    );
                },
            );
            $container->alias(Store::class, StreamDoctrineDbalStore::class);

            if ($configuration->readOnlyStore) {
                $container->decorate(
                    Store::class,
                    StreamReadOnlyStore::class,
                    static function (Container $container, StreamStore $inner) {
                        return new StreamReadOnlyStore(
                            $inner,
                            $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
                        );
                    },
                );
            }

            return;
        }

        throw new InvalidArgumentException(sprintf('Unknown store type "%s".', $configuration->storeType));
    }

    private static function configureSnapshots(Configuration $configuration, Container $container): void
    {
        if ($configuration->snapshotAdapters === []) {
            return;
        }

        $container->bind(AdapterRepository::class, new ArrayAdapterRepository($configuration->snapshotAdapters));

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

    private static function configureAggregates(Configuration $configuration, Container $container): void
    {
        $container->bind(
            AggregateRootMetadataAwareMetadataFactory::class,
            new AggregateRootMetadataAwareMetadataFactory(),
        );
        $container->alias(AggregateRootMetadataFactory::class, AggregateRootMetadataAwareMetadataFactory::class);

        $container->bind(
            AggregateRootRegistry::class,
            (new AttributeAggregateRootRegistryFactory())->create($configuration->aggregates),
        );

        $container->bind(
            DefaultRepositoryManager::class,
            static function (Container $container): DefaultRepositoryManager {
                return new DefaultRepositoryManager(
                    $container->get(AggregateRootRegistry::class),
                    $container->get(Store::class),
                    $container->has(EventBus::class) ? $container->get(EventBus::class) : null,
                    $container->has(SnapshotStore::class) ? $container->get(SnapshotStore::class) : null,
                    $container->get(MessageDecorator::class),
                    $container->get(ClockInterface::class),
                    $container->get(AggregateRootMetadataFactory::class),
                    $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
                );
            },
        );
        $container->alias(RepositoryManager::class, DefaultRepositoryManager::class);
    }

    private static function configureCommands(Configuration $configuration, Container $container): void
    {
        $container->bind(
            ShowCommand::class,
            static function (Container $container) {
                return new ShowCommand(
                    $container->get(Store::class),
                    $container->get(EventSerializer::class),
                    $container->get(HeadersSerializer::class),
                );
            },
        );
        $container->bind(
            ShowAggregateCommand::class,
            static function (Container $container) {
                return new ShowAggregateCommand(
                    $container->get(Store::class),
                    $container->get(EventSerializer::class),
                    $container->get(HeadersSerializer::class),
                    $container->get(AggregateRootRegistry::class),
                    $container->get(AggregateRootMetadataFactory::class),
                );
            },
        );
        $container->bind(
            WatchCommand::class,
            static function (Container $container) {
                return new WatchCommand(
                    $container->get(Store::class),
                    $container->get(EventSerializer::class),
                    $container->get(HeadersSerializer::class),
                );
            },
        );
        $container->bind(
            DebugCommand::class,
            static function (Container $container) {
                return new DebugCommand(
                    $container->get(AggregateRootRegistry::class),
                    $container->get(EventRegistry::class),
                    $container->get(SubscriberAccessorRepository::class),
                );
            },
        );
        $container->bind(
            SubscriptionSetupCommand::class,
            static function (Container $container) {
                return new SubscriptionSetupCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
        $container->bind(
            SubscriptionBootCommand::class,
            static function (Container $container) {
                return new SubscriptionBootCommand(
                    $container->get(SubscriptionEngine::class),
                    $container->has(EventDispatcherInterface::class) ? $container->get(EventDispatcherInterface::class) : null,
                );
            },
        );
        $container->bind(
            SubscriptionRunCommand::class,
            static function (Container $container) {
                return new SubscriptionRunCommand(
                    $container->get(SubscriptionEngine::class),
                    $container->get(Store::class),
                    $container->has(EventDispatcherInterface::class) ? $container->get(EventDispatcherInterface::class) : null,
                );
            },
        );
        $container->bind(
            SubscriptionTeardownCommand::class,
            static function (Container $container) {
                return new SubscriptionTeardownCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
        $container->bind(
            SubscriptionRemoveCommand::class,
            static function (Container $container) {
                return new SubscriptionRemoveCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
        $container->bind(
            SubscriptionStatusCommand::class,
            static function (Container $container) {
                return new SubscriptionStatusCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
        $container->bind(
            SubscriptionPauseCommand::class,
            static function (Container $container) {
                return new SubscriptionPauseCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
        $container->bind(
            SubscriptionReactivateCommand::class,
            static function (Container $container) {
                return new SubscriptionReactivateCommand(
                    $container->get(SubscriptionEngine::class),
                );
            },
        );
    }

    private static function configureSchema(Configuration $configuration, Container $container): void
    {
        $container->bind(
            ChainDoctrineSchemaConfigurator::class,
            static function (Container $container): ChainDoctrineSchemaConfigurator {
                $services = [];

                if ($container->has(Store::class)) {
                    $services[] = $container->get(Store::class);
                }

                if ($container->has(SubscriptionStore::class)) {
                    $services[] = $container->get(SubscriptionStore::class);
                }

                if ($container->has(CipherKeyStore::class)) {
                    $services[] = $container->get(CipherKeyStore::class);
                }

                return new ChainDoctrineSchemaConfigurator(array_filter(
                    $services,
                    static fn ($service) => $service instanceof DoctrineSchemaConfigurator,
                ));
            },
        );
        $container->alias(DoctrineSchemaConfigurator::class, ChainDoctrineSchemaConfigurator::class);

        $container->bind(
            DoctrineSchemaDirector::class,
            static fn (Container $container): DoctrineSchemaDirector => new DoctrineSchemaDirector(
                $container->get(self::CONNECTION_ID),
                $container->get(DoctrineSchemaConfigurator::class),
            ),
        );
        $container->alias(DoctrineSchemaProvider::class, DoctrineSchemaDirector::class);
        $container->alias(SchemaDirector::class, DoctrineSchemaDirector::class);

        $container->bind(DoctrineHelper::class, new DoctrineHelper());

        $container->bind(
            DatabaseCreateCommand::class,
            static fn (Container $container): DatabaseCreateCommand => new DatabaseCreateCommand(
                $container->get(self::CONNECTION_ID),
                $container->get(DoctrineHelper::class),
            ),
        );

        $container->bind(
            DatabaseDropCommand::class,
            static fn (Container $container): DatabaseDropCommand => new DatabaseDropCommand(
                $container->get(self::CONNECTION_ID),
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

    private static function configureMessageLoader(Configuration $configuration, Container $container): void
    {
        $container->bind(
            StoreMessageLoader::class,
            static function (Container $container): StoreMessageLoader {
                return new StoreMessageLoader($container->get(Store::class));
            },
        );
        $container->alias(MessageLoader::class, StoreMessageLoader::class);

        if ($configuration->subscriptionGapDetection === false) {
            return;
        }

        $container->bind(
            GapResolverStoreMessageLoader::class,
            static function (Container $container) use ($configuration): GapResolverStoreMessageLoader {
                return new GapResolverStoreMessageLoader(
                    $container->get(Store::class),
                    $container->get(ClockInterface::class),
                    $configuration->subscriptionGapDetectionRetriesInMs,
                    $configuration->subscriptionGapDetectionWindow,
                );
            },
        );
        $container->alias(MessageLoader::class, GapResolverStoreMessageLoader::class);
    }

    private static function configureSubscription(Configuration $configuration, Container $container): void
    {
        $container->bind(AttributeSubscriberMetadataFactory::class, new AttributeSubscriberMetadataFactory());
        $container->alias(SubscriberMetadataFactory::class, AttributeSubscriberMetadataFactory::class);

        if ($configuration->subscriptionRetryStrategyDefinitions !== []) {
            $container->bind(
                RetryStrategyRepository::class,
                static function (Container $container) use ($configuration): RetryStrategyRepository {
                    /** @var array<string, RetryStrategy> $strategies */
                    $strategies = [];
                    foreach ($configuration->subscriptionRetryStrategyDefinitions as $name => $retryStrategyDefinition) {
                        if ($retryStrategyDefinition['type'] === Configuration::SUBSCRIPTION_RETRY_CLOCK_BASED) {
                            if (!array_key_exists('options', $retryStrategyDefinition)) {
                                throw new InvalidArgumentException(sprintf(
                                    'Missing options for subscription retry strategy "%s".',
                                    $name,
                                ));
                            }

                            $strategies[$name] = new ClockBasedRetryStrategy(
                                $container->get(ClockInterface::class),
                                $retryStrategyDefinition['options']['base_delay'],
                                $retryStrategyDefinition['options']['delay_factor'],
                                $retryStrategyDefinition['options']['max_attempts'],
                            );

                            continue;
                        }

                        if ($retryStrategyDefinition['type'] === Configuration::SUBSCRIPTION_RETRY_NO_RETRY) {
                            $strategies[$name] = new NoRetryStrategy();
                            continue;
                        }

                        if ($retryStrategyDefinition['type'] !== Configuration::SUBSCRIPTION_RETRY_CUSTOM) {
                            throw new InvalidArgumentException(sprintf(
                                'Unknown retry strategy type "%s" for "%s".',
                                $retryStrategyDefinition['type'],
                                $name,
                            ));
                        }

                        $service = $retryStrategyDefinition['service'] ?? null;
                        if ($service === null) {
                            throw new InvalidArgumentException(sprintf('Custom retry strategy "%s" requires a service id.', $name));
                        }

                        $strategies[$name] = $container->get($service);
                    }

                    return new RetryStrategyRepository($strategies, $configuration->subscriptionDefaultRetryStrategy);
                },
            );
        }

        if ($configuration->subscriptionStoreType === Configuration::SUBSCRIPTION_STORE_IN_MEMORY) {
            $container->bind(
                InMemorySubscriptionStore::class,
                static function (Container $container): SubscriptionStore {
                    return new InMemorySubscriptionStore(
                        [],
                        $container->get(ClockInterface::class),
                    );
                },
            );
            $container->alias(SubscriptionStore::class, InMemorySubscriptionStore::class);
        }

        if ($configuration->subscriptionStoreType === Configuration::SUBSCRIPTION_STORE_STATIC_IN_MEMORY) {
            $factory = new class {
                private static InMemorySubscriptionStore|null $store = null;

                public static function create(): InMemorySubscriptionStore
                {
                    if (self::$store === null) {
                        self::$store = new InMemorySubscriptionStore();
                    }

                    return self::$store;
                }
            };

            $container->bind(InMemorySubscriptionStore::class, static fn () => $factory::create());
            $container->alias(SubscriptionStore::class, InMemorySubscriptionStore::class);
        }

        if ($configuration->subscriptionStoreType === Configuration::SUBSCRIPTION_STORE_DBAL) {
            $container->bind(
                DoctrineSubscriptionStore::class,
                static function (Container $container) use ($configuration): SubscriptionStore {
                    return new DoctrineSubscriptionStore(
                        $container->get(self::CONNECTION_ID),
                        $container->get(ClockInterface::class),
                        $configuration->subscriptionStoreTableName,
                    );
                },
            );
            $container->alias(SubscriptionStore::class, DoctrineSubscriptionStore::class);
        }

        if ($configuration->subscriptionStoreType === Configuration::SUBSCRIPTION_STORE_CUSTOM) {
            if ($configuration->subscriptionStoreService === null) {
                throw new InvalidArgumentException('Custom subscription store type requires a subscription store service id.');
            }

            $container->alias(SubscriptionStore::class, $configuration->subscriptionStoreService);
        }

        $container->bind(
            LookupResolver::class,
            static function (Container $container): LookupResolver {
                return new LookupResolver(
                    $container->get(Store::class),
                    $container->get(EventRegistry::class),
                );
            },
        );

        $container->bind(
            MetadataSubscriberAccessorRepository::class,
            static function (Container $container) use ($configuration): MetadataSubscriberAccessorRepository {
                return new MetadataSubscriberAccessorRepository(
                    $configuration->subscribers,
                    $container->get(SubscriberMetadataFactory::class),
                    [
                        $container->get(LookupResolver::class),
                        ...$configuration->subscriptionArgumentResolvers,
                    ],
                );
            },
        );
        $container->alias(SubscriberAccessorRepository::class, MetadataSubscriberAccessorRepository::class);

        $container->bind(
            DefaultCleaner::class,
            new DefaultCleaner($configuration->subscriptionCleanupTaskHandlers),
        );
        $container->alias(Cleaner::class, DefaultCleaner::class);

        $container->bind(
            DefaultSubscriptionEngine::class,
            static function (Container $container): DefaultSubscriptionEngine {
                return new DefaultSubscriptionEngine(
                    $container->get(MessageLoader::class),
                    $container->get(SubscriptionStore::class),
                    $container->get(SubscriberAccessorRepository::class),
                    $container->has(RetryStrategyRepository::class) ? $container->get(RetryStrategyRepository::class) : null,
                    $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
                    $container->get(Cleaner::class),
                );
            },
        );
        $container->alias(SubscriptionEngine::class, DefaultSubscriptionEngine::class);

        if ($configuration->subscriptionThrowOnError) {
            $container->decorate(
                SubscriptionEngine::class,
                ThrowOnErrorSubscriptionEngine::class,
                static function (
                    Container $container,
                    SubscriptionEngine $inner,
                ): ThrowOnErrorSubscriptionEngine {
                    return new ThrowOnErrorSubscriptionEngine($inner);
                },
            );
        }

        if ($configuration->subscriptionCatchUp) {
            $container->decorate(
                SubscriptionEngine::class,
                CatchUpSubscriptionEngine::class,
                static function (Container $container, SubscriptionEngine $inner) use ($configuration,
                ): CatchUpSubscriptionEngine {
                    return new CatchUpSubscriptionEngine(
                        $inner,
                        $configuration->subscriptionCatchUpLimit,
                    );
                },
            );
        }

        if (!$configuration->subscriptionRunAfterAggregateSaveEnabled) {
            return;
        }

        $container->decorate(
            RepositoryManager::class,
            RunSubscriptionEngineRepositoryManager::class,
            static function (Container $container, RepositoryManager $inner) use ($configuration,
            ): RunSubscriptionEngineRepositoryManager {
                return new RunSubscriptionEngineRepositoryManager(
                    $inner,
                    $container->get(SubscriptionEngine::class),
                    $configuration->subscriptionRunAfterAggregateSaveIds,
                    $configuration->subscriptionRunAfterAggregateSaveGroups,
                    $configuration->subscriptionRunAfterAggregateSaveLimit,
                );
            },
        );
    }

    private static function configureStoreMigration(Configuration $configuration, Container $container): void
    {
        if ($configuration->storeMigrationEnabled === false) {
            return;
        }

        $container->bind(
            StoreMigrateCommand::class,
            static function (Container $container) use ($configuration): StoreMigrateCommand {
                return new StoreMigrateCommand(
                    $container->get(Store::class),
                    $container->get(self::NEW_STORE_ID),
                    $configuration->storeMigrationTranslators,
                );
            },
        );

        if ($configuration->storeMigrationType === Configuration::STORE_IN_MEMORY) {
            $container->bind(
                self::NEW_STORE_ID,
                static function (Container $container): InMemoryStore {
                    return new InMemoryStore(
                        [],
                        $container->get(EventRegistry::class),
                        $container->get(ClockInterface::class),
                    );
                },
            );

            return;
        }

        if ($configuration->storeMigrationType === Configuration::STORE_DBAL_STREAM) {
            $container->bind(
                self::NEW_STORE_ID,
                static function (Container $container) use ($configuration): StreamDoctrineDbalStore {
                    return new StreamDoctrineDbalStore(
                        $container->get(self::CONNECTION_ID),
                        $container->get(EventSerializer::class),
                        $container->get(HeadersSerializer::class),
                        $container->get(ClockInterface::class),
                        $configuration->storeMigrationOptions,
                    );
                },
            );

            return;
        }

        if ($configuration->storeMigrationType === Configuration::STORE_CUSTOM) {
            if ($configuration->storeMigrationService === null) {
                throw new InvalidArgumentException('Custom store migration type requires a service id.');
            }

            $container->bind(
                self::NEW_STORE_ID,
                static fn (Container $container): Store => $container->get($configuration->storeMigrationService),
            );

            return;
        }

        throw new InvalidArgumentException(sprintf('Unknown store type "%s"', $configuration->storeMigrationType));
    }
}
