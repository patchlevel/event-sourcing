---
searchable: false
---
# Upgrade 4.0

## Aggregates

### Aggregate Root

Method `aggregateRootId` return typehint has been changed from `AggregateRootId` to `Identifier`.

### Aggregate Root Id

`AggregateRootId` was renamed to `Identifier` and moved to the `Patchlevel\EventSourcing\Identifier` namespace.

Following classes have been moved to the `Patchlevel\EventSourcing\Identifier` namespace too:

* `CustomId`
* `CustomIdBehaviour`
* `RamseyUuidV7Behaviour`
* `Uuid`

Return typehint of `fromString` method has been changed from `self` to `static`.
All typehints of other classes `AggregateRootId` have been changed to `Identifier`.

### Child Aggregate

We removed our experimental feature of child aggregates.
This was our first attempt to split aggregates into smaller parts,
but we found a better way to do this with the `Micro Aggregate` feature.

## Aggregate Repository

Typehints for the `AggregateRepository` have been changed, from `AggregateRootId` to `Identifier`.

## Subscription

The constructor of the `DefaultSubscriptionEngine` class has been changed.

* Instead of passing a `Store` instance, you now need to pass a `MessageLoader` instance.
* Instead of passing a `RetryStrategy` instance, you now need to pass a `RetryStrategyRepository` instance.

before:

```php
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;

/**
 * @var Store $store
 * @var DoctrineSubscriptionStore $subscriptionStore
 * @var MetadataSubscriberAccessorRepository $subscriberAccessorRepository
 * @var RetryStrategy $retryStrategy
 */
$subscriptionEngine = new DefaultSubscriptionEngine(
    $messageLoader,
    $subscriptionStore,
    $subscriberAccessorRepository,
    $retryStrategy,
);
```
after:

```php
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;

/**
 * @var Store $store
 * @var DoctrineSubscriptionStore $subscriptionStore
 * @var MetadataSubscriberAccessorRepository $subscriberAccessorRepository
 * @var RetryStrategy $retryStrategy
 */
$subscriptionEngine = new DefaultSubscriptionEngine(
    new StoreMessageLoader($store),
    $subscriptionStore,
    $subscriberAccessorRepository,
    RetryStrategyRepository::withDefault($retryStrategy),
);
```
### Subscription Engine Commands

The `SubscriptionEngine` interface has been changed.
The methods `setup`, `boot`, `run`, `teardown`, `remove`, `reactivate`, `pause` and `refresh` have been replaced
by a single `execute` method that takes a command object.
The `ids` and `groups` filters, previously passed via `SubscriptionEngineCriteria`,
are now constructor parameters of the command objects.
The `SubscriptionEngineCriteria` is now only used for the `subscriptions` method.

before:

```php
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;

/** @var SubscriptionEngine $subscriptionEngine */
$subscriptionEngine->setup(new SubscriptionEngineCriteria(ids: ['profile_1']), skipBooting: true);
$subscriptionEngine->boot(new SubscriptionEngineCriteria(ids: ['profile_1']), limit: 100);
$subscriptionEngine->run(new SubscriptionEngineCriteria(ids: ['profile_1']), limit: 100);
$subscriptionEngine->teardown(new SubscriptionEngineCriteria(ids: ['profile_1']));
$subscriptionEngine->remove(new SubscriptionEngineCriteria(ids: ['profile_1']));
$subscriptionEngine->reactivate(new SubscriptionEngineCriteria(ids: ['profile_1']));
$subscriptionEngine->pause(new SubscriptionEngineCriteria(ids: ['profile_1']));
$subscriptionEngine->refresh(new SubscriptionEngineCriteria(ids: ['profile_1']));
```
after:

```php
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;

/** @var SubscriptionEngine $subscriptionEngine */
$subscriptionEngine->execute(new Setup(ids: ['profile_1'], skipBooting: true));
$subscriptionEngine->execute(new Boot(ids: ['profile_1'], limit: 100));
$subscriptionEngine->execute(new Run(ids: ['profile_1'], limit: 100));
$subscriptionEngine->execute(new Teardown(ids: ['profile_1']));
$subscriptionEngine->execute(new Remove(ids: ['profile_1']));
$subscriptionEngine->execute(new Reactivate(ids: ['profile_1']));
$subscriptionEngine->execute(new Pause(ids: ['profile_1']));
$subscriptionEngine->execute(new Refresh(ids: ['profile_1']));
```
Further changes:

* The `CanRefreshSubscriptions` interface has been removed. Refresh is now part of the `SubscriptionEngine` interface via the `Refresh` command.
* `ProcessedResult` now extends `Result`, so the `execute` method always returns a `Result`. The `Boot` and `Run` commands return a `ProcessedResult`.
* The `DefaultSubscriptionEngine` accepts an optional `EventDispatcherInterface` as last constructor argument to hook into the engine with own listeners.

### SubscriberHelper and SubscriberUtil

The deprecated `Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberHelper`
and the `Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil` trait have been removed.

If you used them inside a projector to keep the projector id and the table name in sync,
use a constant instead:

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector(self::TABLE)]
final class HotelProjector
{
    // use a const for easier access in the projector & to keep projector id and table name in sync
    private const TABLE = 'hotel';

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /** @return list<array{id: string, name: string, guests: int}> */
    public function getHotels(): array
    {
        return $this->db->fetchAllAssociative(sprintf('SELECT id, name, guests FROM %s;', self::TABLE));
    }

    // ...
}
```

If you still need the subscriber id elsewhere, read it from the metadata instead:

```php
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;

$metadata = (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class);
$subscriberId = $metadata->id;
```

### SubscriberAccessor and RealSubscriberAccessor

The `Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessor` and
`Patchlevel\EventSourcing\Subscription\Subscriber\RealSubscriberAccessor` interfaces have been removed.
Use `Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor` directly.

Accordingly, `SubscriberAccessorRepository::get()` now returns a `MetadataSubscriberAccessor|null`
instead of a `SubscriberAccessor|null`.

The deprecated methods `id()`, `group()` and `runMode()` on `MetadataSubscriberAccessor` have been removed.
Use `->metadata()->id`, `->metadata()->group` and `->metadata()->runMode` instead.

### AggregateIdArgumentResolver

The deprecated `Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\AggregateIdArgumentResolver`
has been removed. Automatically resolving the aggregate id in a stream store is not possible.
Add the aggregate id to your events instead.

### ArgumentMetadata

The subscriber argument resolver now uses `symfony/type-info` to describe argument types.

`Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata` no longer carries a `string $type`
and a `bool $allowsNull` property. Instead it now has a single `Symfony\Component\TypeInfo\Type $type` property.

If you implemented a custom `ArgumentResolver`, adjust it to read the type from the new `Type` object.

## Store

### StreamStore

`StreamStore` interface was merged with `Store` interface.

### DoctrineDbalStore

`DoctrineDbalStore` has been removed in favor of `StreamDoctrineDbalStore`.
And all the associated classes:

* `Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion`
* `Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion`
* `Patchlevel\EventSourcing\Store\DoctrineDbalStore`
* `Patchlevel\EventSourcing\Store\DoctrineDbalStoreStream`

### StreamReadOnlyStore

`StreamReadOnlyStore` was been merged in `ReadOnlyStore`.

## Stream

The stream handling has been reworked. Previously the `Stream` was an interface that every store had to
implement on its own (`ArrayStream`, `StreamDoctrineDbalStoreStream`, `TaggableDoctrineDbalStoreStream`,
`GeneratorStream`, ...). Now there is a single generic implementation that you can reuse.

### Stream interface

The `Patchlevel\EventSourcing\Store\Stream` interface has been removed and replaced by the concrete final
class `Patchlevel\EventSourcing\Message\Stream`.

All stores now return a `Patchlevel\EventSourcing\Message\Stream` from their `load()` method.
The following store specific stream implementations have been removed:

* `Patchlevel\EventSourcing\Store\ArrayStream`
* `Patchlevel\EventSourcing\Store\StreamDoctrineDbalStoreStream`
* `Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStoreStream`
* `Patchlevel\EventSourcing\Subscription\Engine\GeneratorStream`

The new `Stream` class implements `Iterator` and accepts any `iterable<Message>` in its constructor.
The `index()`, `position()`, `end()` and `close()` methods remain available.
In addition there are now the helper methods `toList()`, `toArray()`, `transform()` and `chunk()`.

### Pipe

`Patchlevel\EventSourcing\Message\Pipe` has been removed. Use `Stream::transform()` instead.

before:

```php
use Patchlevel\EventSourcing\Message\Pipe;

$messages = (new Pipe($messages, $translator))->toArray();
```

after:

```php
use Patchlevel\EventSourcing\Message\Stream;

$messages = (new Stream($messages))->transform($translator)->toList();
```

## Message

### AggregateHeader

`Patchlevel\EventSourcing\Aggregate\AggregateHeader` has been removed
and replaced with the following headers:

* `Patchlevel\EventSourcing\Store\Header\StreamNameHeader`
* `Patchlevel\EventSourcing\Store\Header\PlayheadHeader`
* `Patchlevel\EventSourcing\Store\Header\RecordedOnHeader`

### AggregateToStreamHeaderTranslator

`Patchlevel\EventSourcing\Store\AggregateToStreamHeaderTranslator` has been removed.

## Schema

### DoctrineSchemaSubscriber

The `Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber` has been removed.
use the `Patchlevel\EventSourcing\Schema\DoctrineSchemaListener` instead.
