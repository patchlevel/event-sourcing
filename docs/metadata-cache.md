# Metadata Cache

The library reads a lot of information from attributes: which classes are aggregates and events,
which apply method belongs to which event, which subscriber listens to what.
This information is called metadata, and collecting it means scanning directories
and reflecting over classes.

In development that is exactly what you want, because every change is picked up immediately.
In production the classes never change while the process runs, so the same work is repeated on every request.
For this the library ships decorators that cache the metadata in a
[PSR-6](https://www.php-fig.org/psr/psr-6/) or [PSR-16](https://www.php-fig.org/psr/psr-16/) cache.

:::note
The library only provides the decorators, not the cache implementation itself.
You can use [symfony cache](https://symfony.com/doc/current/components/cache.html) or any other
PSR-6 or PSR-16 compatible cache.
:::

## Registry cache

Registries are the hashmaps between names and classes.
Building them means scanning all configured paths, which is the most expensive part of the metadata handling.

The `AggregateRootRegistryFactory` and the `EventRegistryFactory` both have a caching decorator.
You wrap the attribute based factory and pass a cache to it.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootRegistryFactory;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$aggregateRegistryFactory = new Psr6AggregateRootRegistryFactory(
    new AttributeAggregateRootRegistryFactory(),
    $cache,
);

$aggregateRegistry = $aggregateRegistryFactory->create(['src/Domain']);
```
```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventRegistryFactory;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$eventRegistryFactory = new Psr6EventRegistryFactory(
    new AttributeEventRegistryFactory(),
    $cache,
);

$eventRegistry = $eventRegistryFactory->create(['src/Domain']);
```
If you use a PSR-16 cache, take the `Psr16` variants instead. They work the same way.

```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr16EventRegistryFactory;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */
$eventRegistryFactory = new Psr16EventRegistryFactory(
    new AttributeEventRegistryFactory(),
    $cache,
);
```
:::warning
The registry factories cache under a fixed key, `aggregate_root_registry` and `event_registry`.
The paths you pass to `create` are not part of that key.
If you call the same factory with different paths, you get the result of the first call back.
Use a separate factory instance with its own cache for each set of paths.
:::

## Metadata cache

Next to the registries, there is the metadata of a single class.
These factories are called with a class name and return the metadata for it,
so they cache per class name.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootMetadataFactory;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$aggregateMetadataFactory = new Psr6AggregateRootMetadataFactory(
    new AttributeAggregateRootMetadataFactory(),
    $cache,
);
```
```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventMetadataFactory;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$eventMetadataFactory = new Psr6EventMetadataFactory(
    new AttributeEventMetadataFactory(),
    $cache,
);
```
```php
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\Psr6SubscriberMetadataFactory;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$subscriberMetadataFactory = new Psr6SubscriberMetadataFactory(
    new AttributeSubscriberMetadataFactory(),
    $cache,
);
```
For all three there is a `Psr16` variant that takes a PSR-16 cache instead.

:::note
The attribute based metadata factories already keep an in memory cache for the current process.
The PSR decorators add a cache that survives the process.
:::

## Usage

The cached factories are drop-in replacements, so you pass them wherever the library asks
for a factory or a registry.

The registries go into the [repository manager](repository.md) and the serializer,
the aggregate metadata factory is the seventh argument of the `DefaultRepositoryManager`.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRegistryFactory->create(['src/Domain']),
    $store,
    null,
    null,
    new SplitStreamDecorator($eventMetadataFactory),
    null,
    $aggregateMetadataFactory,
);

$serializer = new DefaultEventSerializer(
    $eventRegistryFactory->create(['src/Domain']),
);
```
The subscriber metadata factory goes into the `MetadataSubscriberAccessorRepository`.

```php
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;

$subscriberRepository = new MetadataSubscriberAccessorRepository(
    [/* subscribers... */],
    $subscriberMetadataFactory,
);
```
:::note
Without an explicit factory, the `DefaultRepositoryManager` asks the aggregate class itself
for its metadata. Pass the cached factory as shown above if you want the repository manager to use it.
:::

## Deployment

The cache is keyed by class name and by a fixed registry key, not by the content of your classes.
Nothing invalidates it when you change an attribute.

:::danger
You have to clear the cache on every deployment.
A stale registry makes the library load the wrong class for an event name,
a stale metadata entry makes it call the wrong apply method.
:::

:::tip
Use a separate cache pool for the metadata and clear it as part of your deployment,
in the same step where you warm up your other caches.
Do not enable the cache in development, otherwise new events and subscribers are not picked up.
:::

## Learn more

* [How to register aggregates](aggregate.md)
* [How to register events](events.md)
* [How to create subscribers](subscription.md)
* [How to store events](store.md)
