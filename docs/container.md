# Container

The library consists of many small services: the store, the serializer, the repository manager,
the subscription engine and more. Wiring them by hand is flexible, but a lot of work.
The container does this for you. You describe your setup in one configuration array
and get a [PSR-11](https://www.php-fig.org/psr/psr-11/) container with all services ready to use.

:::tip
If you use symfony, you can use our [symfony bundle](https://patchlevel.dev/docs/event-sourcing-bundle/latest) instead.
The configuration of the container follows the configuration of the bundle.
:::

## Create the container

You create the container with the `Factory`.
The only required option is the database connection,
everything else has a default.

```php
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'aggregates' => ['src/Domain/Hotel'],
    'events' => ['src/Domain/Hotel/Event'],
    'subscription' => [
        'subscribers' => [new HotelProjector($projectionConnection)],
        'sync' => true,
    ],
]);

$hotelRepository = $container->get(RepositoryManager::class)->get(Hotel::class);
```
The services are created lazily, the first time you request them.
You request them by their interface, for example `RepositoryManager`, `CommandBus`, `QueryBus`,
`Store`, `SubscriptionEngine` or `SchemaDirector`.

:::note
The configuration is described as a PHP array shape,
so your IDE and static analysers like PHPStan autocomplete and validate every option.
:::

## Configuration

Every option is optional except the connection.
You only configure what differs from the defaults.
Some rules apply to all options:

* Sections with an `enabled` key can also be set to a bool: `'event_bus' => true` enables the event bus with its defaults.
  If you configure such a section with an array, it is enabled automatically.
* Lists of services like `subscribers`, `upcasters` or `message_decorators` accept objects as well as service ids.
  Service ids are resolved from the container or from an [external container](#framework-integration).
* An invalid configuration, like an unknown option or a wrong type, throws an `InvalidConfiguration` exception
  that names the exact path, for example `Unrecognized option "typ" under "event_sourcing.store"`.

### Connection

The container either creates the doctrine connection from a url or uses an existing connection service.

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Container\Factory;

$connection = DriverManager::getConnection(
    (new DsnParser())->parse('pdo-pgsql://user:secret@localhost/app'),
);

$container = Factory::create([
    'connection' => ['service' => 'app.connection'],
    'services' => ['app.connection' => $connection],
]);
```
With `provide_dedicated_connection`, the container creates a second connection from the url,
which is available as `Doctrine\DBAL\Connection` for your projections.
The store keeps its own connection, so your projections don't interfere with its transactions.

### Store

By default the `StreamDoctrineDbalStore` is used.
You can switch to the `TaggableDoctrineDbalStore`, the `InMemoryStore` or your own store service.

```php
use Patchlevel\EventSourcing\Container\Factory;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'store' => [
        'type' => 'dbal_taggable',
        'options' => ['table_name' => 'hotel_events'],
    ],
]);
```
The `options` are passed to the store, the available options are listed on the [store](store.md) page.
With `read_only`, the stream store is wrapped in a `ReadOnlyStore`.

:::tip
With `dbal_taggable`, the container adds the `PostgreSQLPlatformMiddleware` to the connection created from the `url`,
so a GIN index on the `tags` column is created on PostgreSQL.
If you pass your own connection `service`, you have to register the middleware yourself.
:::

To move your events into another store, enable `migrate_to_new_store`.
This registers the `event-sourcing:store:migrate` [command](cli.md#store-migration-command).

```php
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Message\Translator\ExtractEventTagTranslator;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'store' => [
        'migrate_to_new_store' => [
            'type' => 'dbal_taggable',
            'options' => ['table_name' => 'new_event_store'],
            'translators' => [new ExtractEventTagTranslator()],
        ],
    ],
]);
```
### Subscriptions

The `subscription` section configures the [subscription engine](subscription.md) and its subscribers.

```php
use Patchlevel\EventSourcing\Container\Factory;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'subscription' => [
        'subscribers' => [
            new HotelProjector($projectionConnection),
            new SendCheckInEmailProcessor($mailer),
        ],
        'sync' => [
            'groups' => ['projector'],
            'throw_on_error' => true,
        ],
        'gap_detection' => true,
    ],
]);
```
With `sync`, the subscriptions run directly after an aggregate was saved,
so your projections are up to date in the same request.
For this, a separate catch-up engine is used, the `SubscriptionEngine` used by your workers is not affected.
You can limit it to some subscriptions with `ids` and `groups`.

:::tip
Use `sync` for development and small applications.
For production, run the subscriptions in a [worker](cli.md#run) instead.
:::

With `event_emitter`, subscribers can emit new events into their own `subscription_<id>` stream
with the [EventEmitter](subscription.md#event-emitter-resolver). It is disabled by default.

:::warning
If a subscription is removed, its `subscription_<id>` stream is removed from the event store as well.
:::

:::note
The event emitter does not work with a read only store.
:::

### Hydrator

The `hydrator` section configures how events, headers and snapshots are hydrated.
You can enable the [cryptography](sensitive-data.md) for personal data,
register [upcasters](upcasting.md) and add your own hydrator extensions.

```php
use Patchlevel\EventSourcing\Container\Factory;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'hydrator' => [
        'cryptography' => true,
        'upcasters' => [
            'before_transform' => [new ProfileCreatedEmailLowerCastUpcaster()],
        ],
    ],
]);
```
With the cryptography enabled, the cipher keys are stored in the `cryptography_keys` table.
You can also use your own cipher key store with `cipher_key_store`.

### Services and parameters

With `services` you add your own services to the container.
A closure is called with the container the first time the service is requested,
so your service can use the event sourcing services.

```php
use Patchlevel\EventSourcing\Container\Container;
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;

$container = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'store' => ['type' => 'dbal_taggable'],
    'dcb' => true,
    'command_bus' => [
        'handlers' => [CreateHotelHandler::class],
    ],
    'services' => [
        CreateHotelHandler::class => static fn (Container $container): CreateHotelHandler => new CreateHotelHandler(
            $container->get(DecisionModelBuilder::class),
            $container->get(EventAppender::class),
        ),
    ],
]);
```
Your services are registered last, so a service with the id of a built-in service replaces it.
`parameters` are plain values, for example for the `#[Inject]` attribute of [aggregate handlers](command-bus.md).

### Reference

These are all options with their default values.

```php
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;

$container = Factory::create([
    'connection' => [
        'url' => null,                  // a doctrine dbal url
        'service' => null,              // or the id of an existing connection service
        'provide_dedicated_connection' => false,
    ],
    'store' => [
        'type' => 'dbal_stream',        // dbal_stream, dbal_taggable, in_memory or custom
        'service' => null,              // the store service for the custom type
        'options' => [],                // table_name, locking, lock_id, lock_timeout, keep_index, default_stream_name
        'read_only' => false,
        'migrate_to_new_store' => [
            'enabled' => false,
            'type' => 'dbal_stream',
            'service' => null,
            'options' => [],
            'translators' => [],
        ],
    ],
    'aggregates' => [],                 // paths to your aggregates
    'events' => [],                     // paths to your events
    'headers' => [],                    // paths to your custom headers
    'event_bus' => [
        'enabled' => false,
        'type' => 'default',            // default, psr14 or custom
        'service' => null,              // the psr-14 event dispatcher or the custom event bus
        'listeners' => [],
    ],
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
    'query_bus' => [
        'enabled' => true,
        'handlers' => [],
        'handler_providers' => [],
    ],
    'clock' => [
        'freeze' => null,               // a date, e.g. "2025-01-01 12:00:00", for a frozen clock
        'service' => null,              // or the id of your own PSR-20 clock
    ],
    'logger' => [
        'service' => null,              // the id of a PSR-3 logger
    ],
    'migration' => [
        'enabled' => true,
        'namespace' => 'EventSourcingMigrations',
        'path' => 'migrations',
    ],
    'snapshot_stores' => [],            // name => snapshot adapter or ['type' => 'psr6|psr16|custom', 'service' => '...']
    'subscription' => [
        'subscribers' => [],
        'store' => [
            'type' => 'dbal',           // dbal, in_memory, static_in_memory or custom
            'service' => null,
            'options' => ['table_name' => 'subscriptions'],
        ],
        'retry_strategies' => [
            'default' => [
                'type' => 'clock_based',
                'options' => ['base_delay' => 5, 'delay_factor' => 2.0, 'max_attempts' => 5],
            ],
            'no_retry' => ['type' => 'no_retry'],
        ],
        'default_retry_strategy' => 'default',
        'sync' => [
            'enabled' => false,
            'ids' => [],
            'groups' => [],
            'catch_up_limit' => null,
            'throw_on_error' => false,
        ],
        'gap_detection' => [
            'enabled' => false,
            'retries_in_ms' => [0, 5, 50, 500],
            'detection_window' => 'PT5M',
        ],
        'event_filtered_message_loader' => false,
        'event_emitter' => false,
        'argument_resolvers' => [],
        'cleanup_task_handlers' => [],
    ],
    'dcb' => false,
    'hydrator' => [
        'default_lazy' => false,
        'cryptography' => [
            'enabled' => false,
            'algorithm' => 'aes-128-gcm',
            'cipher_key_store' => null,
        ],
        'lifecycle' => false,
        'extensions' => [],
        'guessers' => [],
        'upcasters' => [
            'before_encoding' => [],
            'before_transform' => [],
        ],
    ],
    'message_decorators' => [],
    'services' => [],
    'parameters' => [],
]);
```
:::note
The `retry_strategies` replace the defaults as a whole.
If you configure your own strategies, the default strategy has to be one of them.
:::

## Framework integration

If you use a framework without an official integration, like Laminas or CakePHP,
or a container like PHP-DI, you connect both containers in two directions:

* Pass your application container as external container.
  Every service id that the event sourcing container doesn't know is looked up there,
  so your subscribers, handlers, logger or connection can come from your application.
* Register the event sourcing services in your application container with `Factory::registerBridges()`,
  so your controllers and services can get the `CommandBus` or `RepositoryManager` injected.

### PHP-DI

```php
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Container\Factory;
use Psr\Log\LoggerInterface;

use function DI\factory;

$container = (new ContainerBuilder())
    ->useAutowiring(true)
    ->addDefinitions([
        Connection::class => $connection,
        LoggerInterface::class => $logger,
    ])
    ->build();

$eventSourcing = Factory::create(
    [
        'connection' => ['service' => Connection::class],
        'logger' => ['service' => LoggerInterface::class],
        'aggregates' => ['src/Domain/Hotel'],
        'events' => ['src/Domain/Hotel/Event'],
        'subscription' => [
            'subscribers' => [HotelProjector::class],
        ],
    ],
    $container,
);

Factory::registerBridges(
    $eventSourcing,
    static function (string $id, callable $service) use ($container): void {
        $container->set($id, factory($service));
    },
);
```
The `HotelProjector` is created by PHP-DI with its dependencies,
and every service of your application can get the event sourcing services injected.

### Laminas

```php
use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Patchlevel\EventSourcing\Container\Factory;

$container = new ServiceManager([
    'services' => [Connection::class => $connection],
    'abstract_factories' => [ReflectionBasedAbstractFactory::class],
]);

$eventSourcing = Factory::create(
    [
        'connection' => ['service' => Connection::class],
        'aggregates' => ['src/Domain/Hotel'],
        'events' => ['src/Domain/Hotel/Event'],
        'subscription' => [
            'subscribers' => [HotelProjector::class],
        ],
    ],
    $container,
);

Factory::registerBridges(
    $eventSourcing,
    static function (string $id, callable $service) use ($container): void {
        $container->setFactory($id, static fn (): object => $service());
    },
);
```
### League Container and CakePHP

The container of CakePHP is based on the [League Container](https://container.thephpleague.com/),
so the integration looks the same for both.

```php
use Doctrine\DBAL\Connection;
use League\Container\Container;
use League\Container\ReflectionContainer;
use Patchlevel\EventSourcing\Container\Factory;

$container = new Container();
$container->delegate(new ReflectionContainer(true));
$container->addShared(Connection::class, $connection);

$eventSourcing = Factory::create(
    [
        'connection' => ['service' => Connection::class],
        'aggregates' => ['src/Domain/Hotel'],
        'events' => ['src/Domain/Hotel/Event'],
        'subscription' => [
            'subscribers' => [HotelProjector::class],
        ],
    ],
    $container,
);

Factory::registerBridges(
    $eventSourcing,
    static function (string $id, callable $service) use ($container): void {
        $container->addShared($id, static fn (): object => $service());
    },
);
```
:::note
Only services created by the event sourcing container are registered as bridges.
Services that come from your application container, like the connection in these examples, stay untouched.
:::

## Console commands

`Factory::commands()` returns all [cli commands](cli.md) of the configured features,
so you can add them to your symfony console application.

```php
use Patchlevel\EventSourcing\Container\Factory;
use Symfony\Component\Console\Application;

$eventSourcing = Factory::create([
    'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app'],
    'aggregates' => ['src/Domain/Hotel'],
    'events' => ['src/Domain/Hotel/Event'],
]);

$cli = new Application('Event-Sourcing CLI');
$cli->addCommands(Factory::commands($eventSourcing));
$cli->run();
```
The [doctrine migrations](cli.md#doctrine-migrations) commands are included as `event-sourcing:migration:*`.

## Learn more

* [How to get started](getting-started.md)
* [How to configure the store](store.md)
* [How to use subscriptions](subscription.md)
* [How to use the cli commands](cli.md)
