<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Closure;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider as CommandBusHandlerProvider;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\QueryBus\HandlerProvider as QueryBusHandlerProvider;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Guesser\Guesser;
use Throwable;

use function array_key_exists;
use function sprintf;

/**
 * The configuration of the event sourcing container.
 *
 * The shape follows the configuration of the symfony bundle. Lists of services like subscribers or upcasters
 * accept objects as well as service ids, which are resolved from the container or the external container.
 * Sections with an "enabled" key can also be configured with a bool as shorthand.
 *
 * The array shapes are generated from the tree, run "make config-shape" after changing it.
 *
 * @phpstan-type StoreType 'dbal_stream'|'dbal_taggable'|'in_memory'|'custom'
 * @phpstan-type SnapshotStoreDefinition array{type?: 'psr6'|'psr16'|'custom', service: string}
 * @phpstan-type NormalizedSnapshotStoreDefinition array{type: 'psr6'|'psr16'|'custom', service: string}
 * @phpstan-type Config array{
 *     connection?: array{
 *         url?: string|null,
 *         service?: string|null,
 *         provide_dedicated_connection?: bool,
 *     },
 *     store?: array{
 *         type?: StoreType,
 *         service?: string|null,
 *         options?: array<string, mixed>,
 *         read_only?: bool,
 *         migrate_to_new_store?: bool|array{
 *             enabled?: bool,
 *             type?: StoreType,
 *             service?: string|null,
 *             options?: array<string, mixed>,
 *             translators?: list<Translator|string>,
 *         },
 *     },
 *     aggregates?: list<string>,
 *     events?: list<string>,
 *     headers?: list<string>,
 *     event_bus?: bool|array{
 *         enabled?: bool,
 *         type?: 'default'|'psr14'|'custom',
 *         service?: string|null,
 *         listeners?: list<object|string>,
 *     },
 *     command_bus?: bool|array{
 *         enabled?: bool,
 *         register_aggregate_handlers?: bool,
 *         handlers?: list<object|string>,
 *         handler_providers?: list<CommandBusHandlerProvider|string>,
 *         instant_retry?: bool|array{
 *             enabled?: bool,
 *             default_max_retries?: positive-int,
 *             default_exceptions?: list<class-string<Throwable>>,
 *         },
 *     },
 *     query_bus?: bool|array{
 *         enabled?: bool,
 *         handlers?: list<object|string>,
 *         handler_providers?: list<QueryBusHandlerProvider|string>,
 *     },
 *     clock?: array{freeze?: string|null, service?: string|null},
 *     logger?: array{service?: string|null},
 *     migration?: bool|array{enabled?: bool, namespace?: string, path?: string},
 *     snapshot_stores?: array<string, SnapshotAdapter|SnapshotStoreDefinition>,
 *     subscription?: array{
 *         subscribers?: list<object|string>,
 *         store?: array{
 *             type?: 'dbal'|'in_memory'|'static_in_memory'|'custom',
 *             service?: string|null,
 *             options?: array{table_name?: string},
 *         },
 *         retry_strategies?: array<string, array{
 *             type: 'clock_based'|'no_retry'|'custom',
 *             service?: string|null,
 *             options?: array<string, mixed>,
 *         }>,
 *         default_retry_strategy?: string,
 *         sync?: bool|array{
 *             enabled?: bool,
 *             ids?: list<string>,
 *             groups?: list<string>,
 *             catch_up_limit?: positive-int|null,
 *             throw_on_error?: bool,
 *         },
 *         gap_detection?: bool|array{enabled?: bool, retries_in_ms?: list<int>, detection_window?: string|null},
 *         event_filtered_message_loader?: bool|array{enabled?: bool},
 *         event_emitter?: bool|array{enabled?: bool},
 *         argument_resolvers?: list<ArgumentResolver|string>,
 *         cleanup_task_handlers?: list<CleanupTaskHandler|string>,
 *     },
 *     dcb?: bool|array{enabled?: bool},
 *     hydrator?: array{
 *         default_lazy?: bool,
 *         cryptography?: bool|array{
 *             enabled?: bool,
 *             algorithm?: non-empty-string,
 *             cipher_key_store?: string|null,
 *         },
 *         lifecycle?: bool|array{enabled?: bool},
 *         extensions?: list<Extension|string>,
 *         guessers?: list<Guesser|string>,
 *         upcasters?: array{
 *             before_encoding?: list<Upcaster|string>,
 *             before_transform?: list<Upcaster|string>,
 *         },
 *     },
 *     message_decorators?: list<MessageDecorator|string>,
 *     services?: array<string, object|Closure(Container): object>,
 *     parameters?: array<string, mixed>,
 * }
 * @phpstan-type NormalizedConfig array{
 *     connection: array{
 *         url: string|null,
 *         service: string|null,
 *         provide_dedicated_connection: bool,
 *     },
 *     store: array{
 *         type: StoreType,
 *         service: string|null,
 *         options: array<string, mixed>,
 *         read_only: bool,
 *         migrate_to_new_store: array{
 *             enabled: bool,
 *             type: StoreType,
 *             service: string|null,
 *             options: array<string, mixed>,
 *             translators: list<Translator|string>,
 *         },
 *     },
 *     aggregates: list<string>,
 *     events: list<string>,
 *     headers: list<string>,
 *     event_bus: array{
 *         enabled: bool,
 *         type: 'default'|'psr14'|'custom',
 *         service: string|null,
 *         listeners: list<object|string>,
 *     },
 *     command_bus: array{
 *         enabled: bool,
 *         register_aggregate_handlers: bool,
 *         handlers: list<object|string>,
 *         handler_providers: list<CommandBusHandlerProvider|string>,
 *         instant_retry: array{
 *             enabled: bool,
 *             default_max_retries: positive-int,
 *             default_exceptions: list<class-string<Throwable>>,
 *         },
 *     },
 *     query_bus: array{
 *         enabled: bool,
 *         handlers: list<object|string>,
 *         handler_providers: list<QueryBusHandlerProvider|string>,
 *     },
 *     clock: array{freeze: string|null, service: string|null},
 *     logger: array{service: string|null},
 *     migration: array{enabled: bool, namespace: string, path: string},
 *     snapshot_stores: array<string, SnapshotAdapter|NormalizedSnapshotStoreDefinition>,
 *     subscription: array{
 *         subscribers: list<object|string>,
 *         store: array{
 *             type: 'dbal'|'in_memory'|'static_in_memory'|'custom',
 *             service: string|null,
 *             options: array{table_name: string},
 *         },
 *         retry_strategies: array<string, array{
 *             type: 'clock_based'|'no_retry'|'custom',
 *             service: string|null,
 *             options: array<string, mixed>,
 *         }>,
 *         default_retry_strategy: string,
 *         sync: array{
 *             enabled: bool,
 *             ids: list<string>,
 *             groups: list<string>,
 *             catch_up_limit: positive-int|null,
 *             throw_on_error: bool,
 *         },
 *         gap_detection: array{enabled: bool, retries_in_ms: list<int>, detection_window: string|null},
 *         event_filtered_message_loader: array{enabled: bool},
 *         event_emitter: array{enabled: bool},
 *         argument_resolvers: list<ArgumentResolver|string>,
 *         cleanup_task_handlers: list<CleanupTaskHandler|string>,
 *     },
 *     dcb: array{enabled: bool},
 *     hydrator: array{
 *         default_lazy: bool,
 *         cryptography: array{enabled: bool, algorithm: non-empty-string, cipher_key_store: string|null},
 *         lifecycle: array{enabled: bool},
 *         extensions: list<Extension|string>,
 *         guessers: list<Guesser|string>,
 *         upcasters: array{
 *             before_encoding: list<Upcaster|string>,
 *             before_transform: list<Upcaster|string>,
 *         },
 *     },
 *     message_decorators: list<MessageDecorator|string>,
 *     services: array<string, object|Closure(Container): object>,
 *     parameters: array<string, mixed>,
 * }
 * @phpstan-import-type Node from ConfigurationNormalizer
 */
final class Configuration
{
    public const STORE_DBAL_STREAM = 'dbal_stream';
    public const STORE_DBAL_TAGGABLE = 'dbal_taggable';
    public const STORE_IN_MEMORY = 'in_memory';
    public const STORE_CUSTOM = 'custom';

    public const EVENT_BUS_DEFAULT = 'default';
    public const EVENT_BUS_PSR14 = 'psr14';
    public const EVENT_BUS_CUSTOM = 'custom';

    public const SUBSCRIPTION_STORE_DBAL = 'dbal';
    public const SUBSCRIPTION_STORE_IN_MEMORY = 'in_memory';
    public const SUBSCRIPTION_STORE_STATIC_IN_MEMORY = 'static_in_memory';
    public const SUBSCRIPTION_STORE_CUSTOM = 'custom';

    public const SUBSCRIPTION_RETRY_CLOCK_BASED = 'clock_based';
    public const SUBSCRIPTION_RETRY_NO_RETRY = 'no_retry';
    public const SUBSCRIPTION_RETRY_CUSTOM = 'custom';

    private const STORE_TYPES = [self::STORE_DBAL_STREAM, self::STORE_DBAL_TAGGABLE, self::STORE_IN_MEMORY, self::STORE_CUSTOM];

    /**
     * Merges the defaults into the given configuration and validates it.
     *
     * @param Config $config
     *
     * @return NormalizedConfig
     *
     * @throws InvalidConfiguration
     */
    public static function normalize(array $config): array
    {
        /** @var NormalizedConfig $normalized */
        $normalized = ConfigurationNormalizer::normalize(self::tree(), $config, 'event_sourcing');

        self::validate($normalized);

        return $normalized;
    }

    /**
     * @internal
     *
     * @return Node
     */
    public static function tree(): array
    {
        $storeType = ConfigurationNormalizer::named(
            'StoreType',
            ConfigurationNormalizer::enum(self::STORE_TYPES, self::STORE_DBAL_STREAM),
        );

        // the options depend on the store, so they are passed through as they are
        $storeOptions = ConfigurationNormalizer::map(ConfigurationNormalizer::variable());

        return ConfigurationNormalizer::named('Config', ConfigurationNormalizer::struct([
            'connection' => ConfigurationNormalizer::struct([
                'url' => ConfigurationNormalizer::string(null),
                'service' => ConfigurationNormalizer::string(null),
                'provide_dedicated_connection' => ConfigurationNormalizer::bool(false),
            ]),
            'store' => ConfigurationNormalizer::struct([
                'type' => $storeType,
                'service' => ConfigurationNormalizer::string(null),
                'options' => $storeOptions,
                'read_only' => ConfigurationNormalizer::bool(false),
                'migrate_to_new_store' => ConfigurationNormalizer::toggle(false, [
                    'type' => $storeType,
                    'service' => ConfigurationNormalizer::string(null),
                    'options' => $storeOptions,
                    'translators' => ConfigurationNormalizer::services(Translator::class),
                ]),
            ]),
            'aggregates' => ConfigurationNormalizer::stringList(),
            'events' => ConfigurationNormalizer::stringList(),
            'headers' => ConfigurationNormalizer::stringList(),
            'event_bus' => ConfigurationNormalizer::toggle(false, [
                'type' => ConfigurationNormalizer::enum(
                    [self::EVENT_BUS_DEFAULT, self::EVENT_BUS_PSR14, self::EVENT_BUS_CUSTOM],
                    self::EVENT_BUS_DEFAULT,
                ),
                'service' => ConfigurationNormalizer::string(null),
                'listeners' => ConfigurationNormalizer::services(),
            ]),
            'command_bus' => ConfigurationNormalizer::toggle(true, [
                'register_aggregate_handlers' => ConfigurationNormalizer::bool(true),
                'handlers' => ConfigurationNormalizer::services(),
                'handler_providers' => ConfigurationNormalizer::services(CommandBusHandlerProvider::class),
                'instant_retry' => ConfigurationNormalizer::toggle(true, [
                    'default_max_retries' => ConfigurationNormalizer::int(3, 1),
                    'default_exceptions' => ConfigurationNormalizer::typed(
                        'list<class-string<\\Throwable>>',
                        ConfigurationNormalizer::stringList([AggregateOutdated::class]),
                    ),
                ]),
            ]),
            'query_bus' => ConfigurationNormalizer::toggle(true, [
                'handlers' => ConfigurationNormalizer::services(),
                'handler_providers' => ConfigurationNormalizer::services(QueryBusHandlerProvider::class),
            ]),
            'clock' => ConfigurationNormalizer::struct([
                'freeze' => ConfigurationNormalizer::string(null),
                'service' => ConfigurationNormalizer::string(null),
            ]),
            'logger' => ConfigurationNormalizer::struct([
                'service' => ConfigurationNormalizer::string(null),
            ]),
            'migration' => ConfigurationNormalizer::toggle(true, [
                'namespace' => ConfigurationNormalizer::string('EventSourcingMigrations', false),
                'path' => ConfigurationNormalizer::string('migrations', false),
            ]),
            'snapshot_stores' => ConfigurationNormalizer::map(
                ConfigurationNormalizer::objectOr(
                    SnapshotAdapter::class,
                    ConfigurationNormalizer::named('SnapshotStoreDefinition', ConfigurationNormalizer::struct([
                        'type' => ConfigurationNormalizer::enum(['psr6', 'psr16', 'custom'], 'psr6'),
                        'service' => ConfigurationNormalizer::requiredString(),
                    ])),
                ),
            ),
            'subscription' => ConfigurationNormalizer::struct([
                'subscribers' => ConfigurationNormalizer::services(),
                'store' => ConfigurationNormalizer::struct([
                    'type' => ConfigurationNormalizer::enum(
                        [
                            self::SUBSCRIPTION_STORE_DBAL,
                            self::SUBSCRIPTION_STORE_IN_MEMORY,
                            self::SUBSCRIPTION_STORE_STATIC_IN_MEMORY,
                            self::SUBSCRIPTION_STORE_CUSTOM,
                        ],
                        self::SUBSCRIPTION_STORE_DBAL,
                    ),
                    'service' => ConfigurationNormalizer::string(null),
                    'options' => ConfigurationNormalizer::struct([
                        'table_name' => ConfigurationNormalizer::string('subscriptions', false),
                    ]),
                ]),
                'retry_strategies' => ConfigurationNormalizer::map(
                    ConfigurationNormalizer::struct([
                        'type' => ConfigurationNormalizer::requiredEnum([
                            self::SUBSCRIPTION_RETRY_CLOCK_BASED,
                            self::SUBSCRIPTION_RETRY_NO_RETRY,
                            self::SUBSCRIPTION_RETRY_CUSTOM,
                        ]),
                        'service' => ConfigurationNormalizer::string(null),
                        'options' => ConfigurationNormalizer::map(ConfigurationNormalizer::variable()),
                    ]),
                    [
                        'default' => [
                            'type' => self::SUBSCRIPTION_RETRY_CLOCK_BASED,
                            'service' => null,
                            'options' => ['base_delay' => 5, 'delay_factor' => 2.0, 'max_attempts' => 5],
                        ],
                        'no_retry' => [
                            'type' => self::SUBSCRIPTION_RETRY_NO_RETRY,
                            'service' => null,
                            'options' => [],
                        ],
                    ],
                ),
                'default_retry_strategy' => ConfigurationNormalizer::string('default', false),
                'sync' => ConfigurationNormalizer::toggle(false, [
                    'ids' => ConfigurationNormalizer::stringList(),
                    'groups' => ConfigurationNormalizer::stringList(),
                    'catch_up_limit' => ConfigurationNormalizer::int(null, 1),
                    'throw_on_error' => ConfigurationNormalizer::bool(false),
                ]),
                'gap_detection' => ConfigurationNormalizer::toggle(false, [
                    'retries_in_ms' => ConfigurationNormalizer::intList([0, 5, 50, 500]),
                    'detection_window' => ConfigurationNormalizer::string('PT5M'),
                ]),
                'event_filtered_message_loader' => ConfigurationNormalizer::toggle(false, []),
                'event_emitter' => ConfigurationNormalizer::toggle(false, []),
                'argument_resolvers' => ConfigurationNormalizer::services(ArgumentResolver::class),
                'cleanup_task_handlers' => ConfigurationNormalizer::services(CleanupTaskHandler::class),
            ]),
            'dcb' => ConfigurationNormalizer::toggle(false, []),
            'hydrator' => ConfigurationNormalizer::struct([
                'default_lazy' => ConfigurationNormalizer::bool(false),
                'cryptography' => ConfigurationNormalizer::toggle(false, [
                    'algorithm' => ConfigurationNormalizer::typed(
                        'non-empty-string',
                        ConfigurationNormalizer::string(OpensslCipherKeyFactory::DEFAULT_METHOD, false),
                    ),
                    'cipher_key_store' => ConfigurationNormalizer::string(null),
                ]),
                'lifecycle' => ConfigurationNormalizer::toggle(false, []),
                'extensions' => ConfigurationNormalizer::services(Extension::class),
                'guessers' => ConfigurationNormalizer::services(Guesser::class),
                'upcasters' => ConfigurationNormalizer::struct([
                    'before_encoding' => ConfigurationNormalizer::services(Upcaster::class),
                    'before_transform' => ConfigurationNormalizer::services(Upcaster::class),
                ]),
            ]),
            'message_decorators' => ConfigurationNormalizer::services(MessageDecorator::class),
            'services' => ConfigurationNormalizer::map(ConfigurationNormalizer::typed(
                'object|\\Closure(\\Patchlevel\\EventSourcing\\Container\\Container): object',
                ConfigurationNormalizer::object(),
            )),
            'parameters' => ConfigurationNormalizer::map(ConfigurationNormalizer::variable()),
        ]));
    }

    /** @param NormalizedConfig $config */
    private static function validate(array $config): void
    {
        $connection = $config['connection'];

        if ($connection['url'] === null && $connection['service'] === null) {
            throw new InvalidConfiguration('Either "event_sourcing.connection.url" or "event_sourcing.connection.service" is required.');
        }

        if ($connection['url'] !== null && $connection['service'] !== null) {
            throw new InvalidConfiguration('Only one of "event_sourcing.connection.url" and "event_sourcing.connection.service" can be set.');
        }

        if ($connection['provide_dedicated_connection'] && $connection['url'] === null) {
            throw new InvalidConfiguration('"event_sourcing.connection.provide_dedicated_connection" is only possible with "url".');
        }

        $requiresService = [
            'store' => $config['store']['type'] === self::STORE_CUSTOM,
            'store.migrate_to_new_store' => $config['store']['migrate_to_new_store']['enabled']
                && $config['store']['migrate_to_new_store']['type'] === self::STORE_CUSTOM,
            'event_bus' => $config['event_bus']['enabled'] && $config['event_bus']['type'] !== self::EVENT_BUS_DEFAULT,
            'subscription.store' => $config['subscription']['store']['type'] === self::SUBSCRIPTION_STORE_CUSTOM,
        ];

        $services = [
            'store' => $config['store']['service'],
            'store.migrate_to_new_store' => $config['store']['migrate_to_new_store']['service'],
            'event_bus' => $config['event_bus']['service'],
            'subscription.store' => $config['subscription']['store']['service'],
        ];

        foreach ($requiresService as $path => $required) {
            if ($required && $services[$path] === null) {
                throw new InvalidConfiguration(sprintf('"event_sourcing.%s.service" is required for this type.', $path));
            }
        }

        foreach ($config['subscription']['retry_strategies'] as $name => $retryStrategy) {
            if ($retryStrategy['type'] === self::SUBSCRIPTION_RETRY_CUSTOM && $retryStrategy['service'] === null) {
                throw new InvalidConfiguration(sprintf(
                    '"event_sourcing.subscription.retry_strategies.%s.service" is required for this type.',
                    $name,
                ));
            }
        }

        if (!array_key_exists($config['subscription']['default_retry_strategy'], $config['subscription']['retry_strategies'])) {
            throw new InvalidConfiguration(sprintf(
                'The default retry strategy "%s" is not defined in "event_sourcing.subscription.retry_strategies".',
                $config['subscription']['default_retry_strategy'],
            ));
        }

        if ($config['store']['read_only'] && $config['store']['type'] !== self::STORE_DBAL_STREAM) {
            throw new InvalidConfiguration('"event_sourcing.store.read_only" is only supported by the "dbal_stream" store.');
        }

        if ($config['subscription']['event_emitter']['enabled'] && $config['store']['read_only']) {
            throw new InvalidConfiguration('"event_sourcing.subscription.event_emitter" does not support a read only store.');
        }

        if ($config['dcb']['enabled'] && $config['store']['type'] === self::STORE_DBAL_STREAM) {
            throw new InvalidConfiguration('"event_sourcing.dcb" requires a store with tag support, e.g. "dbal_taggable".');
        }

        if (
            $config['subscription']['gap_detection']['enabled']
            && $config['subscription']['event_filtered_message_loader']['enabled']
        ) {
            throw new InvalidConfiguration(
                '"event_sourcing.subscription.gap_detection" and "event_sourcing.subscription.event_filtered_message_loader" can not be combined.',
            );
        }
    }
}
