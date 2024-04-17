# Snapshots

Some aggregates can have a large number of events.
This is not a problem if there are a few hundred.
But if the number gets bigger at some point, then loading and rebuilding can become slow.
The `snapshot` system can be used to control this.

!!! note

    In oure benchmarks we can load 10 000 events for one aggregate in 50ms.
    Of course, this can vary from system to system.
    
Normally, the events are all applied again on the aggregate in order to rebuild the current state.
With a `snapshot`, we can shorten the way in which we temporarily save the current state of the aggregate.
When loading it is checked whether the snapshot exists.
If a hit exists, the aggregate is created with the help of the snapshot.
A check is then made to see whether further events have existed since the snapshot
and these are then also applied on the aggregate.
Here, however, only the last events are loaded from the database and not all.

## Configuration

First of all you have to define a snapshot store. This store may have multiple adapters for different caches.
These caches also need a name so that you can determine which aggregates should be stored in which cache.

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;

$snapshotStore = new DefaultSnapshotStore([
    'default' => new Psr16SnapshotAdapter($defaultCache),
    'other_cache' => new Psr16SnapshotAdapter($otherCache),
]);
```
After creating the snapshot store, you need to pass that store to the DefaultRepositoryManager.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 * @var SnapshotStore $snapshotStore
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    null,
    $snapshotStore,
);
```
!!! note

    You can read more about Repository [here](./repository.md).
    
Next we need to tell the Aggregate to take a snapshot of it. We do this using the snapshot attribute.
There we also specify where it should be saved.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```
When taking a snapshot, all properties are extracted and saved.
When loading, this data is written back to the properties.
In other words, in the end everything has to be serializable.
To ensure this, the same system is used as for the events.
You can define normalizers to bring the properties into the correct format.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer]
    public Uuid $id;
    public string $name;
    #[DateTimeImmutableNormalizer]
    public DateTimeImmutable $createdAt;

    // ...
}
```
!!! danger

    If anything changes in the properties of the aggregate, then the cache must be cleared.
    Or the snapshot version needs to be changed so that the previous snapshot is invalid.
    
!!! warning

    In the end it the complete aggregate must be serializeable as json, also the aggregate Id.
    
!!! note

    The [hydrator](https://github.com/patchlevel/hydrator) is used internally and you can use all of its features.
    You can find more about normalizer also [here](normalizer.md).
    
### Snapshot batching

Since the loading of events in itself is quite fast and only becomes noticeably slower with thousands of events,
we do not need to create a snapshot after each event. That would also have a negative impact on performance.
Instead, we can also create a snapshot after `n` events.
The remaining events that are not in the snapshot are then loaded from store.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default', batch: 1000)]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```
### Snapshot versioning

Whenever something changes on the aggregate, the previous snapshot must be discarded.
You can do this by removing the entire snapshot cache when deploying.
But that can be quickly forgotten. It is much easier to specify a snapshot version.
This snapshot version is also saved in the snapshot cache.
When loading, the versions are compared and if they do not match,
the snapshot is discarded and the aggregate is rebuilt from scratch.
The new snapshot is then created automatically.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default', version: '2')]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```
!!! warning

    If the snapshots are discarded, a load peak can occur since the aggregates have to be rebuilt.
    You should update the snapshot version only when necessary.
    
!!! tip

    You can also use uuids for the snapshot version.
    
## Adapter

We offer a few `SnapshotAdapter` implementations that you can use.
But not a direct implementation of a cache.
There are many good libraries out there that address this problem,
and before we reinvent the wheel, choose one of them.
Since there is a psr-6 and psr-16 standard, there are plenty of libraries.
Here are a few listed:

* [symfony cache](https://symfony.com/doc/current/components/cache.html)
* [laminas cache](https://docs.laminas.dev/laminas-cache/)
* [scrapbook](https://www.scrapbook.cash/)

### psr-6

A `Psr6SnapshotAdapter`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-6/).

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$adapter = new Psr6SnapshotAdapter($cache);
```
### psr-16

A `Psr16SnapshotAdapter`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-16/).

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */
$adapter = new Psr16SnapshotAdapter($cache);
```
### in memory

A `InMemorySnapshotAdapter` that can be used for test purposes.

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;

$adapter = new InMemorySnapshotAdapter();
```
## Usage

The snapshot store is automatically used by the repository and takes care of saving and loading.
But you can also use the snapshot store yourself.

### Save

This allows you to save the aggregate as a snapshot:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;

/**
 * @var SnapshotStore $snapshotStore
 * @var AggregateRoot $aggregate
 */
$snapshotStore->save($aggregate);
```
!!! danger

    If the state of an aggregate is saved as a snapshot without being saved to the event store (database), 
    it can lead to data loss or broken aggregates!
    
### Load

You can also load an aggregate from the snapshot store:

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;

$id = Uuid::fromString('229286ff-6f95-4df6-bc72-0a239fe7b284');

/** @var SnapshotStore $snapshotStore */
$aggregate = $snapshotStore->load(Profile::class, $id);
```
The method returns the Aggregate if it was loaded successfully.
If the aggregate was not found, then a `SnapshotNotFound` is thrown.
And if the version is no longer correct and the snapshot is therefore invalid, then a `SnapshotVersionInvalid` is thrown.

!!! warning

    The aggregate may be in an old state as the snapshot may lag behind. 
    You still have to bring the aggregate up to date by loading the missing events from the event store.
    