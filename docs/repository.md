# Repository

A `repository` takes care of storing and loading the `aggregates`.
The [design pattern](https://martinfowler.com/eaaCatalog/repository.html) of the same name is also used.

Every aggregate needs a repository to be stored. 
And each repository is only responsible for one aggregate.

## Create

We offer two implementations. One is a `DefaultRepository` that only reads or writes the data from one store. 
And a `SnapshotRepository` that holds a state of the aggregate in a cache 
so that loading and rebuilding of the aggregate is faster.

Both repositories implement the `Repository` interface. 
This interface can be used for the typehints so that a change is possible at any time.

### Default Repository

The default repository acts directly with the `store` and therefore needs one.
The [event bus](./event_bus.md) is used as a further parameter to dispatch new events.
Finally, the `aggregate` class is needed, which aggregates the repository should take care of.

```php
use Patchlevel\EventSourcing\Repository\Repository;

$repository = new Repository($store, $eventBus, Profile::class);
```

> :book: You can find out more about stores [here](./store.md)

### Snapshot Repository

The `SnapshotRepository` is instantiated just like the DefaultRepository, 
except that it also needs a `SnapshotStore` to load and save the `Snapshots`.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepository;use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;

$snapshot = new Psr6SnapshotAdapter(/* ... */);
$repository = new DefaultRepository($store, $eventBus, Profile::class, $snapshot);
```

> :warning: The aggregate must inherit from the SnapshotableAggregateRoot

> :book: You can find out more about snapshots [here](./snapshots.md)

## Usage

Each `repository` has three methods that are responsible for loading an `aggregate`, 
saving it or checking whether it exists.

### Save

An `aggregate` can be `saved`. 
All new events that have not yet been written to the database are fetched from the aggregate. 
These events are then also append to the database. 
After the events have been written, 
the new events are dispatched on the [event bus](./event_bus.md).

```php
$profile = Profile::create('david.badura@patchlevel.de');

$repository->save($profile);
```

> :book: All events are written to the database with one transaction in order to ensure data consistency.

### Load

An `aggregate` can be loaded using the `load` method. 
All events for the aggregate are loaded from the database and the current state is rebuilt.

```php
$profile = $repository->load('229286ff-6f95-4df6-bc72-0a239fe7b284');
```

> :warning: You can only fetch one aggregate at a time and don't do any complex queries either. 
> Projections are used for this purpose.

> :book: The repository ensures that only one instance per aggregate is returned. 
> A strict instance comparison is therefore easily possible.

### Has

You can also check whether an `aggregate` with a certain id exists. 
It is checked whether any event with this id exists in the database.

```php
if($repository->has('229286ff-6f95-4df6-bc72-0a239fe7b284')) {
    // ...
}
```

> :book: The query is fast and does not load any event. 
> This means that the state of the aggregate is not rebuild either.