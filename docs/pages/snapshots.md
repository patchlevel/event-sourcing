# Snapshots

Some aggregates can have a large number of events.
This is not a problem if there are a few hundred.
But if the number gets bigger at some point, then loading and rebuilding can become slow.
The `snapshot` system can be used to control this.

Normally, the events are all executed again on the aggregate in order to rebuild the current state.
With a `snapshot`, we can shorten the way in which we temporarily save the current state of the aggregate.
When loading it is checked whether the snapshot exists.
If a hit exists, the aggregate is built up with the help of the snapshot.
A check is then made to see whether further events have existed since the snapshot
and these are then also executed on the aggregate.
Here, however, only the last events are loaded from the database and not all.

## Configuration

First of all you have to define a snapshot store. This store may have multiple adapters for different caches.
These caches also need a name so that you can determine which aggregates should be stored in which cache.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;

$snapshotStore = new DefaultSnapshotStore([
    'default' => new Psr16SnapshotAdapter($defaultCache),
    'other_cache' => new Psr16SnapshotAdapter($otherCache),
]);
```

After creating the snapshot store, you need to pass that store to the DefaultRepositoryManager.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;

$snapshotStore = // ...

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus,
    $snapshotStore
);
```

!!! note

    You can read more about Repository [here](./repository.md).

Next we need to tell the Aggregate to take a snapshot of it. We do this using the snapshot attribute.
There we also specify where it should be saved.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends AggregateRoot
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
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends AggregateRoot
{
    public string $id;
    public string $name,
    #[Normalize(new DateTimeImmutableNormalizer())]
    public DateTimeImmutable $createdAt;

    // ...
}
```

!!! danger

    If anything changes in the properties of the aggregate, then the cache must be cleared.
    Or the snapshot version needs to be changed so that the previous snapshot is invalid.

!!! warning

    In the end it has to be possible to serialize it as json.

!!! note

    You can find more about normalizer [here](normalizer.md).

### Snapshot batching

Since the loading of events in itself is quite fast and only becomes noticeably slower with thousands of events,
we do not need to create a snapshot after each event. That would also have a negative impact on performance.
Instead, we can also create a snapshot after `N` events.
The remaining events that are not in the snapshot are then loaded from store.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default', batch: 1000)]
final class Profile extends AggregateRoot
{
    // ...
}
```

### Snapshot versioning

Whenever something changes on the aggregate, the previous snapshot must be discarded.
You can do this by removing the entire snapshot cache when deploying.
But that can be quickly forgotten. It is much easier to specify a snapshot version.
This snapshot version is also saved. When loading, the versions are compared and if they do not match,
the snapshot is discarded and the aggregate is rebuilt from scratch.
The new aggregate is then saved again as a snapshot.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate('profile')]
#[Snapshot('default', version: '2')]
final class Profile extends AggregateRoot
{
    // ...
}
```

!!! warning

    If the snapshots are discarded, a load peak can occur since the aggregates have to be rebuilt.

!!! tip

    You can also use uuids for the snapshot version.

## Adapter

We offer a few `SnapshotAdapter` implementations that you can use.
But not a direct implementation of a cache.
There are many good libraries out there that address this problem,
and before we reinvent the wheel, choose one of them.
Since there is a psr-6 and psr-16 standard, there are plenty of libraries.
Here are a few listed:

- [symfony cache](https://symfony.com/doc/current/components/cache.html)
- [laminas cache](https://docs.laminas.dev/laminas-cache/)
- [scrapbook](https://www.scrapbook.cash/)

### psr6

A `Psr6SnapshotAdapter`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-6/).

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;

$adapter = new Psr6SnapshotAdapter($cache);
```

### psr16

A `Psr16SnapshotAdapter`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-16/).

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;

$adapter = new Psr16SnapshotAdapter($cache);
```

### in memory

A `InMemorySnapshotAdapter` that can be used for test purposes.

```php
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;

$adapter = new InMemorySnapshotAdapter();
```
