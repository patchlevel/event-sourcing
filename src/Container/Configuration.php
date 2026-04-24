<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use DateInterval;
use DateTimeImmutable;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider as CommandBusHandlerProvider;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\QueryBus\HandlerProvider as QueryBusHandlerProvider;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Guesser\Guesser;
use Throwable;

use function array_values;

/**
 * @phpstan-type ServiceMap array<string, object>
 * @phpstan-type StoreOptions array{
 *     table_name?: string,
 *     aggregate_id_type?: 'string'|'uuid',
 *     locking?: bool,
 *     lock_id?: int,
 *     lock_timeout?: int,
 *     kernel_reset?: bool
 * }
 * @phpstan-type SnapshotAdapterMap array<string, SnapshotAdapter>
 * @phpstan-type SnapshotStoreDefinition array{type: 'psr6'|'psr16'|'custom', service: string}
 * @phpstan-type SnapshotStoreDefinitions array<string, SnapshotStoreDefinition>
 * @phpstan-type RetryStrategyType 'clock_based'|'no_retry'|'custom'
 * @phpstan-type RetryStrategyDefinition array{
 *     type: RetryStrategyType,
 *     service?: string,
 *     options?: array<string, mixed>
 * }
 * @phpstan-type RetryStrategyDefinitions array<string, RetryStrategyDefinition>
 * @phpstan-type StoreMigrationConfig array{
 *     enabled: bool,
 *     type: 'dbal_aggregate'|'dbal_stream'|'in_memory'|'custom',
 *     service: string|null,
 *     options: StoreOptions,
 *     translators: list<Translator>
 * }
 */
final class Configuration
{
    public const STORE_DBAL_STREAM = 'dbal_stream';
    public const STORE_IN_MEMORY = 'in_memory';
    public const STORE_CUSTOM = 'custom';

    public const EVENT_BUS_DEFAULT = 'default';
    public const EVENT_BUS_PSR14 = 'psr14';
    public const EVENT_BUS_CUSTOM = 'custom';

    public const SUBSCRIPTION_STORE_IN_MEMORY = 'in_memory';
    public const SUBSCRIPTION_STORE_DBAL = 'dbal';
    public const SUBSCRIPTION_STORE_STATIC_IN_MEMORY = 'static_in_memory';
    public const SUBSCRIPTION_STORE_CUSTOM = 'custom';

    public const SUBSCRIPTION_RETRY_CLOCK_BASED = 'clock_based';
    public const SUBSCRIPTION_RETRY_NO_RETRY = 'no_retry';
    public const SUBSCRIPTION_RETRY_CUSTOM = 'custom';

    /** @var list<string> */
    public array $aggregates = [];

    /** @var list<string> */
    public array $events = [];

    /** @var list<string> */
    public array $headers = [];

    /** @var list<object> */
    public array $listeners = [];

    /** @var ServiceMap */
    public array $services = [];
    /** @var array<string, mixed> */
    public array $parameters = [];

    /** @var StoreOptions */
    public array $storeOptions = [];

    /** @var SnapshotAdapterMap */
    public array $snapshotAdapters = [];

    /** @var list<Upcaster> */
    public array $upcasters = [];

    /** @var list<MessageDecorator> */
    public array $messageDecorators = [];

    /** @var list<Extension> */
    public array $hydratorExtensions = [];

    /** @var list<CommandBusHandlerProvider> */
    public array $commandHandlerProviders = [];

    /** @var list<QueryBusHandlerProvider> */
    public array $queryHandlerProviders = [];

    /** @var list<object> */
    public array $subscribers = [];

    /** @var list<ArgumentResolver> */
    public array $subscriptionArgumentResolvers = [];

    /** @var list<CleanupTaskHandler> */
    public array $subscriptionCleanupTaskHandlers = [];

    /** @var RetryStrategyDefinitions */
    public array $subscriptionRetryStrategyDefinitions = [];

    /** @var list<Translator> */
    public array $storeMigrationTranslators = [];
    public string $storeType = self::STORE_IN_MEMORY;
    public bool $readOnlyStore = false;
    public bool $eventBusEnabled = false;
    public string $eventBusType = self::EVENT_BUS_DEFAULT;
    public string|null $connectionService = null;
    public string|null $storeService = null;
    public string|null $eventBusService = null;
    public string|null $clockService = null;
    public DateTimeImmutable|null $frozenClock = null;
    public string|null $loggerService = null;
    public bool $hydratorDefaultLazy = false;
    public bool $hydratorLifecycleEnabled = false;
    public bool $hydratorStackCryptographyEnabled = false;
    /** @var non-empty-string */
    public string $hydratorStackCryptographyAlgorithm = 'aes128';
    public bool $hydratorStackCryptographyLegacyMetadataMapping = true;
    public string|null $hydratorStackCryptographyCipherKeyStoreService = null;
    public bool $commandBusInstantRetry = false;

    /** @var positive-int  */
    public int $commandBusInstantRetryDefaultMaxRetries = 3;
    /** @var list<class-string<Throwable>> */
    public array $commandBusInstantRetryDefaultExceptions = [AggregateOutdated::class];
    public string $subscriptionStoreType = self::SUBSCRIPTION_STORE_IN_MEMORY;
    public string|null $subscriptionStoreService = null;
    public string $subscriptionStoreTableName = 'subscriptions';
    public string $subscriptionDefaultRetryStrategy = 'default';
    public bool $subscriptionThrowOnError = false;
    public bool $subscriptionCatchUp = false;
    public int|null $subscriptionCatchUpLimit = null;
    /** @var list<string>|null */
    public array|null $subscriptionRunAfterAggregateSaveIds = null;
    /** @var list<string>|null */
    public array|null $subscriptionRunAfterAggregateSaveGroups = null;
    /** @var positive-int|null  */
    public int|null $subscriptionRunAfterAggregateSaveLimit = null;
    public bool $subscriptionGapDetection = false;
    /** @var list<int> */
    public array $subscriptionGapDetectionRetriesInMs = [0, 5, 50, 500];
    public DateInterval|null $subscriptionGapDetectionWindow = null;
    public bool $migrationEnabled = false;
    public string $migrationNamespace = 'EventSourcingMigrations';
    public string $migrationPath = 'migrations';
    public bool $storeMigrationEnabled = false;
    public string $storeMigrationType = self::STORE_IN_MEMORY;
    public string|null $storeMigrationService = null;
    /** @var StoreOptions */
    public array $storeMigrationOptions = [];

    /** @var array<Guesser> */
    public array $guesser = [];
    public string|null $connectionUrl = null;
    public bool $dedicatedProjectionConnection = false;
    public bool $subscriptionRunAfterAggregateSaveEnabled = false;

    public static function createWithConnectionUrl(string $connectionUrl): self
    {
        return (new self())
            ->withConnectionUrl($connectionUrl)
            ->withStreamStore()
            ->withSubscriptionDBALStore();
    }

    public static function createWithConnectionService(object $connectionService): self
    {
        return (new self())
            ->withService('connection.service', $connectionService)
            ->withConnectionService('connection.service')
            ->withStreamStore()
            ->withSubscriptionDBALStore();
    }

    /**
     * @param array<string> $aggregatePaths
     * @param array<string> $eventPaths
     */
    public function withDefaultSettings(
        array $aggregatePaths = [],
        array $eventPaths = [],
    ): self {
        return clone $this
            ->withAggregates(...$aggregatePaths)
            ->withEvents(...$eventPaths)
            ->withHydratorStackCryptography()
            ->withLazyHydrator()
            ->withHydratorLifecycle()
            ->withCommandBusInstantRetry()
            ->withSubscriptionGapDetection();
    }

    public function withService(string $id, object $service): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->services[$id] = $service;

        return $newConfiguration;
    }

    public function withParameter(string $id, mixed $parameter): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->parameters[$id] = $parameter;

        return $newConfiguration;
    }

    public function withAggregates(string ...$aggregates): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->aggregates = array_values($aggregates);

        return $newConfiguration;
    }

    public function withEvents(string ...$events): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->events = array_values($events);

        return $newConfiguration;
    }

    public function withHeaders(string ...$headers): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->headers = array_values($headers);

        return $newConfiguration;
    }

    /** @param StoreOptions $options */
    public function withStreamStore(array $options = [], bool $readOnly = false): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->storeType = self::STORE_DBAL_STREAM;
        $newConfiguration->storeOptions = $options;
        $newConfiguration->readOnlyStore = $readOnly;

        return $newConfiguration;
    }

    /** @param StoreOptions $options */
    public function withInMemoryStore(array $options = []): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->storeType = self::STORE_IN_MEMORY;
        $newConfiguration->storeOptions = $options;

        return $newConfiguration;
    }

    public function withCustomStore(string $store): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->storeType = self::STORE_CUSTOM;
        $newConfiguration->storeService = $store;

        return $newConfiguration;
    }

    public function withConnectionUrl(string $url): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->connectionUrl = $url;

        return $newConfiguration;
    }

    public function withConnectionService(string|null $connectionService): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->connectionService = $connectionService;
        $newConfiguration->connectionUrl = null;
        $newConfiguration->dedicatedProjectionConnection = false;

        return $newConfiguration;
    }

    public function withDedicatedProjectionConnection(): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->dedicatedProjectionConnection = true;

        return $newConfiguration;
    }

    public function withEventBus(string $type = self::EVENT_BUS_DEFAULT): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->eventBusEnabled = true;
        $newConfiguration->eventBusType = $type;

        return $newConfiguration;
    }

    public function withEventBusService(string|null $eventBusService): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->eventBusEnabled = true;
        $newConfiguration->eventBusService = $eventBusService;

        return $newConfiguration;
    }

    public function withClockService(string|null $clockService): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->clockService = $clockService;

        return $newConfiguration;
    }

    public function withFrozenClock(DateTimeImmutable $frozenClock): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->frozenClock = $frozenClock;

        return $newConfiguration;
    }

    public function withLoggerService(string|null $loggerService): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->loggerService = $loggerService;

        return $newConfiguration;
    }

    /** @param list<Upcaster> $upcasters */
    public function withUpcasters(array $upcasters): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->upcasters = $upcasters;

        return $newConfiguration;
    }

    /** @param list<MessageDecorator> $messageDecorators */
    public function withMessageDecorators(array $messageDecorators): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->messageDecorators = $messageDecorators;

        return $newConfiguration;
    }

    /** @param list<Extension> $hydratorExtensions */
    public function withHydratorExtensions(array $hydratorExtensions): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->hydratorExtensions = $hydratorExtensions;

        return $newConfiguration;
    }

    /** @param SnapshotAdapterMap $snapshotAdapters */
    public function withSnapshotAdapters(array $snapshotAdapters): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->snapshotAdapters = $snapshotAdapters;

        return $newConfiguration;
    }

    public function withLazyHydrator(): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->hydratorDefaultLazy = true;

        return $newConfiguration;
    }

    public function withHydratorLifecycle(): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->hydratorLifecycleEnabled = true;

        return $newConfiguration;
    }

    public function withHydratorStackCryptography(
        bool $enabled = true,
        string $algorithm = 'aes128',
        bool $legacyMetadataMapping = true,
        string|null $cipherKeyStoreService = null,
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->hydratorStackCryptographyEnabled = $enabled;
        $newConfiguration->hydratorStackCryptographyAlgorithm = $algorithm === '' ? 'aes128' : $algorithm;
        $newConfiguration->hydratorStackCryptographyLegacyMetadataMapping = $legacyMetadataMapping;
        $newConfiguration->hydratorStackCryptographyCipherKeyStoreService = $cipherKeyStoreService;

        return $newConfiguration;
    }

    /** @param list<CommandBusHandlerProvider> $commandHandlerProviders */
    public function withCommandHandlerProviders(array $commandHandlerProviders): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->commandHandlerProviders = $commandHandlerProviders;

        return $newConfiguration;
    }

    /**
     * @param positive-int                  $defaultMaxRetries
     * @param list<class-string<Throwable>> $defaultExceptions
     */
    public function withCommandBusInstantRetry(
        int $defaultMaxRetries = 3,
        array $defaultExceptions = [AggregateOutdated::class],
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->commandBusInstantRetry = true;
        $newConfiguration->commandBusInstantRetryDefaultMaxRetries = $defaultMaxRetries;
        $newConfiguration->commandBusInstantRetryDefaultExceptions = $defaultExceptions;

        return $newConfiguration;
    }

    /** @param list<QueryBusHandlerProvider> $queryHandlerProviders */
    public function withQueryHandlerProviders(array $queryHandlerProviders): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->queryHandlerProviders = $queryHandlerProviders;

        return $newConfiguration;
    }

    /** @param list<object> $subscribers */
    public function withSubscribers(array $subscribers): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscribers = $subscribers;

        return $newConfiguration;
    }

    /** @param list<ArgumentResolver> $subscriptionArgumentResolvers */
    public function withSubscriptionArgumentResolvers(array $subscriptionArgumentResolvers): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionArgumentResolvers = $subscriptionArgumentResolvers;

        return $newConfiguration;
    }

    /** @param list<CleanupTaskHandler> $subscriptionCleanupTaskHandlers */
    public function withSubscriptionCleanupTaskHandlers(array $subscriptionCleanupTaskHandlers): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionCleanupTaskHandlers = $subscriptionCleanupTaskHandlers;

        return $newConfiguration;
    }

    /** @param positive-int $maxAttempts */
    public function withSubscriptionRetryDefaults(
        int $baseDelay = 5,
        float $delayFactor = 2.0,
        int $maxAttempts = 5,
    ): self {
        return (clone $this)->withSubscriptionRetryStrategyDefinitions([
            'default' => [
                'type' => 'clock_based',
                'options' => [
                    'base_delay' => $baseDelay,
                    'delay_factor' => $delayFactor,
                    'max_attempts' => $maxAttempts,
                ],
            ],
        ]);
    }

    /** @param RetryStrategyDefinitions $definitions */
    public function withSubscriptionRetryStrategyDefinitions(
        array $definitions,
        string $defaultRetryStrategy = 'default',
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionRetryStrategyDefinitions = $definitions;
        $newConfiguration->subscriptionDefaultRetryStrategy = $defaultRetryStrategy;

        return $newConfiguration;
    }

    public function withSubscriptionDBALStore(string $tableName = 'subscriptions'): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionStoreType = self::SUBSCRIPTION_STORE_DBAL;
        $newConfiguration->subscriptionStoreService = null;
        $newConfiguration->subscriptionStoreTableName = $tableName;

        return $newConfiguration;
    }

    public function withSubscriptionInMemoryStore(string $tableName = 'subscriptions'): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionStoreType = self::SUBSCRIPTION_STORE_IN_MEMORY;
        $newConfiguration->subscriptionStoreService = null;
        $newConfiguration->subscriptionStoreTableName = $tableName;

        return $newConfiguration;
    }

    public function withSubscriptionStaticInMemoryStore(string $tableName = 'subscriptions'): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionStoreType = self::SUBSCRIPTION_STORE_STATIC_IN_MEMORY;
        $newConfiguration->subscriptionStoreService = null;
        $newConfiguration->subscriptionStoreTableName = $tableName;

        return $newConfiguration;
    }

    public function withSubscriptionCustomStore(string $storeService): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionStoreType = self::SUBSCRIPTION_STORE_CUSTOM;
        $newConfiguration->subscriptionStoreService = $storeService;

        return $newConfiguration;
    }

    public function withSubscriptionEngineThrowOnError(): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionThrowOnError = true;

        return $newConfiguration;
    }

    public function withSubscriptionEngineCatchUp(int|null $catchUpLimit = null): self
    {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionCatchUp = true;
        $newConfiguration->subscriptionCatchUpLimit = $catchUpLimit;

        return $newConfiguration;
    }

    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function withRunSubscriptionsAfterAggregateSave(
        array|null $ids = null,
        array|null $groups = null,
        int|null $limit = null,
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionRunAfterAggregateSaveEnabled = true;
        $newConfiguration->subscriptionRunAfterAggregateSaveIds = $ids;
        $newConfiguration->subscriptionRunAfterAggregateSaveGroups = $groups;
        $newConfiguration->subscriptionRunAfterAggregateSaveLimit = $limit;

        return $newConfiguration;
    }

    /** @param list<int> $retriesInMs */
    public function withSubscriptionGapDetection(
        array $retriesInMs = [0, 5, 50, 500],
        DateInterval|null $detectionWindow = null,
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->subscriptionGapDetection = true;
        $newConfiguration->subscriptionGapDetectionRetriesInMs = $retriesInMs;
        $newConfiguration->subscriptionGapDetectionWindow = $detectionWindow;

        return $newConfiguration;
    }

    public function withMigration(
        bool $enabled = true,
        string $namespace = 'EventSourcingMigrations',
        string $path = 'migrations',
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->migrationEnabled = $enabled;
        $newConfiguration->migrationNamespace = $namespace;
        $newConfiguration->migrationPath = $path;

        return $newConfiguration;
    }

    /**
     * @param StoreOptions     $options
     * @param list<Translator> $translators
     */
    public function withStoreMigration(
        bool $enabled = true,
        string $type = self::STORE_IN_MEMORY,
        string|null $service = null,
        array $options = [],
        array $translators = [],
    ): self {
        $newConfiguration = clone $this;
        $newConfiguration->storeMigrationEnabled = $enabled;
        $newConfiguration->storeMigrationType = $type;
        $newConfiguration->storeMigrationService = $service;
        /** @var StoreOptions $migrationOptions */
        $migrationOptions = $options;
        $newConfiguration->storeMigrationOptions = $migrationOptions;
        $newConfiguration->storeMigrationTranslators = $translators;

        return $newConfiguration;
    }
}
