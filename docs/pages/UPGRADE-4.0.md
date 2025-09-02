# Upgrade 4.0

## Aggregates

### Child Aggregate

We removed our experimental feature of child aggregates.
This was our first attempt to split aggregates into smaller parts,
but we found a better way to do this with the `Micro Aggregate` feature.

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
## Store

### DoctrineDbalStore

`DoctrineDbalStore` has been removed in favor of `StreamDoctrineDbalStore`.
And all the associated classes:

* `Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion`
* `Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion`
* `Patchlevel\EventSourcing\Store\DoctrineDbalStore`
* `Patchlevel\EventSourcing\Store\DoctrineDbalStoreStream`
* `Patchlevel\EventSourcing\Store\ReadOnlyStore`

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
