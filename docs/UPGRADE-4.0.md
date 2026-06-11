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
