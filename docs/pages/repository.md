# Repository

A `repository` takes care of storing and loading the `aggregates`.
He is also responsible for building [messages](message.md) from the events
and optionally dispatching them to the event bus.

## Create a repository

The best way to create a repository is to use the `DefaultRepositoryManager`.
This helps to build the repository correctly.

The `DefaultRepositoryManager` needs some services to work.
For one, it needs [AggregateRootRegistry](aggregate.md#aggregate-root-registry) so that it knows which aggregates exist.
And the [store](store.md), which is then given to the repository so that it can save and load the events at the end.

After plugging the `DefaultRepositoryManager` together, you can create the repository associated with the aggregate.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
);

$repository = $repositoryManager->get(Profile::class);
```
!!! note

    The same repository instance is always returned for a specific aggregate.
    
### Event Bus

You can pass an event bus to the `DefaultRepositoryManager` to dispatch events synchronously.
This will be done after the events are saved in the store outside the transaction.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Store\Store;

$eventBus = DefaultEventBus::create([/* listeners */]);

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus,
);

$repository = $repositoryManager->get(Profile::class);
```
!!! warning

    If you use the event bus, you should be aware that the events are dispatched synchronously.
    You may encounter [at least once](https://softwaremill.com/message-delivery-and-deduplication-strategies/) problems.
    
!!! note

    You can find out more about event bus [here](event_bus.md).
    
!!! tip

    In most cases it is better to react to events asynchronously, 
    that's why we recommend the subscription engine.
    More information can be found [here](subscription.md).
    
### Snapshots

Loading events for an aggregate is superfast.
You can have thousands of events in the database that load in a few milliseconds and build the corresponding aggregate.

But at some point you realize that it takes time. To counteract this there is a snapshot store.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\Store;

$adapter = new Psr16SnapshotAdapter($cache);
$snapshotStore = new DefaultSnapshotStore(['default' => $adapter]);

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    null,
    $snapshotStore,
);

$repository = $repositoryManager->get(Profile::class);
```
!!! note

    You can find out more about snapshots [here](snapshots.md).
    
### Decorator

If you want to add more metadata to the message, like e.g. an application id, then you can use decorators.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Store\Store;

$decorator = new ApplicationIdDecorator();

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    null,
    null,
    $decorator,
);

$repository = $repositoryManager->get(Profile::class);
```
!!! note

    You can find out more about message decorator [here](message_decorator.md).
    
!!! tip

    If you have multiple decorators, you can use the `ChainMessageDecorator` to chain them.
    
## Use the repository

Each `repository` has three methods that are responsible for loading an `aggregate`,
saving it or checking whether it exists.

### Save an aggregate

An `aggregate` can be `saved`.
All new events that have not yet been written to the database are fetched from the aggregate.
These events are then also append to the database.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;

$id = Uuid::generate();
$profile = Profile::create($id, 'david.badura@patchlevel.de');

/** @var Repository $repository */
$repository->save($profile);
```
!!! Warning

    All events are written to the database with one transaction in order to ensure data consistency.
    If an exception occurs during the save process, 
    the transaction is rolled back and the aggregate is not valid anymore.
    You can not save the aggregate again and you need to load it again.
    
!!! note

    Due to the nature of the aggregate having a playhead, 
    we have a unique constraint that ensures that no race condition happens here.
    
### Load an aggregate

An `aggregate` can be loaded using the `load` method.
All events for the aggregate are loaded from the database and the current state is rebuilt.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;

$id = Uuid::fromString('229286ff-6f95-4df6-bc72-0a239fe7b284');

/** @var Repository $repository */
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
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;

$id = Uuid::fromString('229286ff-6f95-4df6-bc72-0a239fe7b284');

/** @var Repository $repository */
if ($repository->has($id)) {
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
        $this->repository->save($profile);
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
* [How to use the event bus](event_bus.md)
* [How to create messages](message.md)
