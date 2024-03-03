# Repository

A `repository` takes care of storing and loading the `aggregates` in the [event store](store.md).
He is also responsible for building [messages](event_bus.md) from the events and then dispatching them to the event bus.

Every aggregate needs a repository to be stored. 
And each repository is only responsible for one aggregate.

## Create a repository

The best way to create a repository is to use the `DefaultRepositoryManager`.
This helps to build the repository correctly.

The `DefaultRepositoryManager` needs some services to work. 
For one, it needs [AggregateRootRegistry](aggregate.md#aggregate-root-registry) so that it knows which aggregates exist. 
The [store](store.md), which is then given to the repository so that it can save and load the events at the end. 
And the [EventBus](event_bus.md) to publish the new events.

After plugging the `DefaultRepositoryManager` together, you can create the repository associated with the aggregate.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus
);

$repository = $repositoryManager->get(Profile::class);
```

!!! note

    The same repository instance is always returned for a specific aggregate.

### Snapshots

Loading events for an aggregate is superfast. 
You can have thousands of events in the database that load in a few milliseconds and build the corresponding aggregate.

But at some point you realize that it takes time. To counteract this there is a snapshot store.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;

$adapter = new Psr16SnapshotAdapter($cache);
$snapshotStore = new DefaultSnapshotStore([
    'default' => $adapter
]);

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus,
    $snapshotStore
);

$repository = $repositoryManager->get(Profile::class);
```

!!! note

    You can find out more about snapshots [here](snapshots.md).

### Decorator

If you want to add more metadata to the message, like e.g. an application id, then you can use decorators.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;

$decorator = new ApplicationIdDecorator();

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus,
    null,
    $decorator
);

$repository = $repositoryManager->get(Profile::class);
```

!!! note

    You can find out more about message decorator [here](message_decorator.md).

## Use the repository

Each `repository` has three methods that are responsible for loading an `aggregate`, 
saving it or checking whether it exists.

### Save an aggregate

An `aggregate` can be `saved`. 
All new events that have not yet been written to the database are fetched from the aggregate. 
These events are then also append to the database. 
After the events have been written, 
the new events are dispatched on the [event bus](./event_bus.md).

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;

$id = Uuid::v7();
$profile = Profile::create($id, 'david.badura@patchlevel.de');

$repository->save($profile);
```

!!! note

    All events are written to the database with one transaction in order to ensure data consistency.

!!! tip

    If you want to make sure that dispatching events and storing events is transaction safe, 
    then you should look at the [outbox](outbox.md) pattern.

### Load an aggregate

An `aggregate` can be loaded using the `load` method. 
All events for the aggregate are loaded from the database and the current state is rebuilt.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;

$id = Uuid::fromString('229286ff-6f95-4df6-bc72-0a239fe7b284');
$profile = $repository->load($id);
```

!!! warning

    When the method is called, the aggregate is always reloaded and rebuilt from the database.

!!! note

    You can only fetch one aggregate at a time and don't do any complex queries either. 
    Projections are used for this purpose.

### Has an aggregate

You can also check whether an `aggregate` with a certain id exists. 
It is checked whether any event with this id exists in the database.

```php
$id = Uuid::fromString('229286ff-6f95-4df6-bc72-0a239fe7b284');

if($repository->has($id)) {
    // ...
}
```

!!! note

    The query is fast and does not load any event. 
    This means that the state of the aggregate is not rebuild either.

## Custom Repository

In clean code you want to have explicit type hints for the repositories
so that you don't accidentally use the wrong repository.
It would also help in frameworks with a dependency injection container,
as this allows the services to be autowired.
However, you cannot inherit from our repository implementations.
Instead, you just have to wrap these repositories.
This also gives you more type security.

```php
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

class ProfileRepository 
{
    /** @var Repository<Profile>  */
    private Repository $repository;

    public function __construct(RepositoryManager $repositoryManager) 
    {
        $this->repository = $repositoryManager->get(Profile::class);
    }
    
    public function load(ProfileId $id): Profile 
    {
        return $this->repository->load($id);
    }
    
    public function save(Profile $profile): void 
    {
        return $this->repository->save($profile);
    }
    
    public function has(ProfileId $id): bool 
    {
        return $this->repository->has($id);
    }
}
```

## Learn more

* [How to create an aggregate](aggregate.md)
* [How to create an event](events.md)
* [How to work with the store](store.md)
* [How to use snapshots](snapshots.md)
* [How to split streams](split_stream.md)