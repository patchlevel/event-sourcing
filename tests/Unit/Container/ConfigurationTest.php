<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container;

use Patchlevel\EventSourcing\Container\Configuration;
use Patchlevel\EventSourcing\Container\ConfigurationNormalizer;
use Patchlevel\EventSourcing\Container\InvalidConfiguration;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Configuration::class)]
#[CoversClass(ConfigurationNormalizer::class)]
final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame(
            [
                'connection' => ['url' => 'sqlite3:///:memory:', 'service' => null, 'provide_dedicated_connection' => false],
                'store' => [
                    'type' => 'dbal_stream',
                    'service' => null,
                    'options' => [],
                    'read_only' => false,
                    'migrate_to_new_store' => [
                        'enabled' => false,
                        'type' => 'dbal_stream',
                        'service' => null,
                        'options' => [],
                        'translators' => [],
                    ],
                ],
                'aggregates' => [],
                'events' => [],
                'headers' => [],
                'event_bus' => ['enabled' => false, 'type' => 'default', 'service' => null, 'listeners' => []],
                'command_bus' => [
                    'enabled' => true,
                    'register_aggregate_handlers' => true,
                    'handlers' => [],
                    'handler_providers' => [],
                    'instant_retry' => [
                        'enabled' => true,
                        'default_max_retries' => 3,
                        'default_exceptions' => [AggregateOutdated::class],
                    ],
                ],
                'query_bus' => ['enabled' => true, 'handlers' => [], 'handler_providers' => []],
                'clock' => ['freeze' => null, 'service' => null],
                'logger' => ['service' => null],
                'migration' => ['enabled' => true, 'namespace' => 'EventSourcingMigrations', 'path' => 'migrations'],
                'snapshot_stores' => [],
                'subscription' => [
                    'subscribers' => [],
                    'store' => ['type' => 'dbal', 'service' => null, 'options' => ['table_name' => 'subscriptions']],
                    'retry_strategies' => [
                        'default' => [
                            'type' => 'clock_based',
                            'service' => null,
                            'options' => ['base_delay' => 5, 'delay_factor' => 2.0, 'max_attempts' => 5],
                        ],
                        'no_retry' => ['type' => 'no_retry', 'service' => null, 'options' => []],
                    ],
                    'default_retry_strategy' => 'default',
                    'sync' => [
                        'enabled' => false,
                        'ids' => [],
                        'groups' => [],
                        'catch_up_limit' => null,
                        'throw_on_error' => false,
                    ],
                    'gap_detection' => ['enabled' => false, 'retries_in_ms' => [0, 5, 50, 500], 'detection_window' => 'PT5M'],
                    'event_filtered_message_loader' => ['enabled' => false],
                    'event_emitter' => ['enabled' => false],
                    'argument_resolvers' => [],
                    'cleanup_task_handlers' => [],
                ],
                'dcb' => ['enabled' => false],
                'hydrator' => [
                    'default_lazy' => false,
                    'cryptography' => ['enabled' => false, 'algorithm' => 'aes-128-gcm', 'cipher_key_store' => null],
                    'lifecycle' => ['enabled' => false],
                    'extensions' => [],
                    'guessers' => [],
                    'upcasters' => ['before_encoding' => [], 'before_transform' => []],
                ],
                'message_decorators' => [],
                'services' => [],
                'parameters' => [],
            ],
            Configuration::normalize(['connection' => ['url' => 'sqlite3:///:memory:']]),
        );
    }

    public function testBoolShorthandForToggles(): void
    {
        $config = Configuration::normalize([
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'event_bus' => true,
            'command_bus' => false,
            'subscription' => ['gap_detection' => true],
        ]);

        self::assertSame(['enabled' => true, 'type' => 'default', 'service' => null, 'listeners' => []], $config['event_bus']);
        self::assertFalse($config['command_bus']['enabled']);
        self::assertTrue($config['subscription']['gap_detection']['enabled']);
        self::assertSame([0, 5, 50, 500], $config['subscription']['gap_detection']['retries_in_ms']);
    }

    public function testConfiguredToggleIsEnabled(): void
    {
        $config = Configuration::normalize([
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'subscription' => ['sync' => ['ids' => ['profile']]],
        ]);

        self::assertSame(
            ['enabled' => true, 'ids' => ['profile'], 'groups' => [], 'catch_up_limit' => null, 'throw_on_error' => false],
            $config['subscription']['sync'],
        );
    }

    public function testRetryStrategiesReplaceDefaults(): void
    {
        $config = Configuration::normalize([
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'subscription' => [
                'retry_strategies' => ['default' => ['type' => 'clock_based', 'options' => ['max_attempts' => 10]]],
            ],
        ]);

        self::assertSame(
            ['default' => ['type' => 'clock_based', 'service' => null, 'options' => ['max_attempts' => 10]]],
            $config['subscription']['retry_strategies'],
        );
    }

    public function testStoreOptionsArePassedThrough(): void
    {
        $config = Configuration::normalize([
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => [
                'type' => 'custom',
                'service' => 'app.store',
                'options' => ['table_name' => 'my_event_store', 'custom_option' => ['foo' => 'bar']],
            ],
        ]);

        self::assertSame(
            ['table_name' => 'my_event_store', 'custom_option' => ['foo' => 'bar']],
            $config['store']['options'],
        );
    }

    public function testServicesAcceptObjectsAndIds(): void
    {
        $decorator = new SplitStreamDecorator(new AttributeEventMetadataFactory());

        $config = Configuration::normalize([
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'message_decorators' => [$decorator, 'app.message_decorator'],
        ]);

        self::assertSame([$decorator, 'app.message_decorator'], $config['message_decorators']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'unknown option' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'store' => ['typ' => 'in_memory']],
            'Unrecognized option "typ" under "event_sourcing.store". Available options are "type", "service", "options", "read_only", "migrate_to_new_store".',
        ];

        yield 'unknown root option' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'foo' => true],
            'Unrecognized option "foo" under "event_sourcing".',
        ];

        yield 'invalid type' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'store' => ['read_only' => 'yes']],
            'Invalid type for path "event_sourcing.store.read_only". Expected "bool", but got "string".',
        ];

        yield 'invalid enum value' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'store' => ['type' => 'dbal']],
            'The value "dbal" is not allowed for path "event_sourcing.store.type". Permissible values: "dbal_stream", "dbal_taggable", "in_memory", "custom".',
        ];

        yield 'invalid service' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'message_decorators' => [new stdClass()]],
            'Invalid type for path "event_sourcing.message_decorators.0". Expected "Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator" or "string", but got "stdClass".',
        ];

        yield 'invalid list' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'aggregates' => ['src' => 'src/Domain']],
            'Invalid type for path "event_sourcing.aggregates". Expected "list", but got "array".',
        ];

        yield 'too small int' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'subscription' => ['sync' => ['catch_up_limit' => 0]]],
            'The value 0 is too small for path "event_sourcing.subscription.sync.catch_up_limit". Should be greater than or equal to 1.',
        ];

        yield 'missing required value' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'snapshot_stores' => ['default' => ['type' => 'psr6']]],
            'The child config "event_sourcing.snapshot_stores.default.service" must be configured.',
        ];

        yield 'missing connection' => [
            [],
            'Either "event_sourcing.connection.url" or "event_sourcing.connection.service" is required.',
        ];

        yield 'url and service' => [
            ['connection' => ['url' => 'sqlite3:///:memory:', 'service' => 'app.connection']],
            'Only one of "event_sourcing.connection.url" and "event_sourcing.connection.service" can be set.',
        ];

        yield 'dedicated connection with service' => [
            ['connection' => ['service' => 'app.connection', 'provide_dedicated_connection' => true]],
            '"event_sourcing.connection.provide_dedicated_connection" is only possible with "url".',
        ];

        yield 'custom store without service' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'store' => ['type' => 'custom']],
            '"event_sourcing.store.service" is required for this type.',
        ];

        yield 'custom event bus without service' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'event_bus' => ['type' => 'psr14']],
            '"event_sourcing.event_bus.service" is required for this type.',
        ];

        yield 'custom retry strategy without service' => [
            [
                'connection' => ['url' => 'sqlite3:///:memory:'],
                'subscription' => ['retry_strategies' => ['default' => ['type' => 'custom']]],
            ],
            '"event_sourcing.subscription.retry_strategies.default.service" is required for this type.',
        ];

        yield 'unknown default retry strategy' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'subscription' => ['default_retry_strategy' => 'foo']],
            'The default retry strategy "foo" is not defined in "event_sourcing.subscription.retry_strategies".',
        ];

        yield 'read only taggable store' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'store' => ['type' => 'dbal_taggable', 'read_only' => true]],
            '"event_sourcing.store.read_only" is only supported by the "dbal_stream" store.',
        ];

        yield 'event emitter with read only store' => [
            [
                'connection' => ['url' => 'sqlite3:///:memory:'],
                'store' => ['read_only' => true],
                'subscription' => ['event_emitter' => true],
            ],
            '"event_sourcing.subscription.event_emitter" does not support a read only store.',
        ];

        yield 'dcb with stream store' => [
            ['connection' => ['url' => 'sqlite3:///:memory:'], 'dcb' => true],
            '"event_sourcing.dcb" requires a store with tag support, e.g. "dbal_taggable".',
        ];

        yield 'gap detection with event filtered message loader' => [
            [
                'connection' => ['url' => 'sqlite3:///:memory:'],
                'subscription' => ['gap_detection' => true, 'event_filtered_message_loader' => true],
            ],
            '"event_sourcing.subscription.gap_detection" and "event_sourcing.subscription.event_filtered_message_loader" can not be combined.',
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage($message);

        /** @phpstan-ignore argument.type */
        Configuration::normalize($config);
    }
}
