# Upgrading to v3

We are only providing here the most important changes in detail. For the full BC-Break list go to
the [full BC-Break list](#Full-BC-Break-list)

## Detailed change list

### Outsourced packages

The `Worker` is now an extra package [patchlevel/worker](https://github.com/patchlevel/worker). 
The `Hydrator` is now an extra package [patchlevel/hydrator](https://github.com/patchlevel/hydrator).

### Aggregates

#### Aggregate Root

The `AggregateRoot` base class is now an interface. This enables users to create their own implementation and to be a 
little more decoupled from our library. We are still providing implementations for an AggregateRoot which can be used
by using a trait. Right now there are two implementations available `AggregateRootBehaviour` and 
`AggregateRootAttributeBehaviour`. And for convinience there is also a new abstract class which you can extend from 
`BasicAggregateRoot` with this class the upgrade should work seemless. Under the hood it is implementing the new 
interface and using the `AggregateRootAttributeBehaviour`.

Before:
```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    // ...
}
```

After (Using new basic class):
```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```

After (Using interface and trait):

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRootAttributeBehaviour;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile implements AggregateRoot
{
    use AggregateRootAttributeBehaviour;
    // ...
}
```

#### Aggregate Root ID

We introduced the `#[Id]` Attribute which is used to mark the aggregate root id. This is again a step for more 
decoupling and enables you to freely design your Aggregates API. The implementation of `AggregateRoot::aggregateRootId` 
is not needed anymore. 

The aggregate root id now needs to implement the `AggregateRootId` interface instead of just being a string. This 
enabled us to have more types safety internally. Regarding that we provide a `Uuid` implementation which is using 
`ramsey/uuid` under the hood and a `CustomId` implementation which is expecting a `string` which could be used if you 
are not using uuids. You can also provide your own implementation and use that.

Before:
```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private ProfileId $id;

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}

// ProfileId.php
final class ProfileId
{
    private function __construct(private string $id)
    {
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
```

After:
```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}

// ProfileId.php
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

final class ProfileId implements AggregateRootId
{
    private function __construct(private string $id) 
    {
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
```

### Events

### Schema


### Store & Pipeline


### Projection



### EventBus


### Snapshot


### Clock


## Full BC-Break list

# Added
- [BC] Method count() was added to interface Patchlevel\EventSourcing\Store\Store
- [BC] Method transactional() was added to interface Patchlevel\EventSourcing\Store\Store

# Changed
- [BC] Class Patchlevel\EventSourcing\Aggregate\AggregateRoot became an interface
- [BC] The return type of Patchlevel\EventSourcing\Aggregate\AggregateRoot#aggregateRootId() changed from string to the non-covariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The return type of Patchlevel\EventSourcing\Aggregate\AggregateRoot#aggregateRootId() changed from string to Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] Method releaseEvents() of class Patchlevel\EventSourcing\Aggregate\AggregateRoot changed from concrete to abstract
- [BC] Method createFromEvents() of class Patchlevel\EventSourcing\Aggregate\AggregateRoot changed from concrete to abstract
- [BC] The parameter $events of Patchlevel\EventSourcing\Aggregate\AggregateRoot::createFromEvents() changed from array to iterable
- [BC] Method playhead() of class Patchlevel\EventSourcing\Aggregate\AggregateRoot changed from concrete to abstract
- [BC] The number of required arguments for Exception#__construct() increased from 0 to 1
- [BC] The parameter $applyMethods of Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#__construct() changed from array to a non-contravariant string
- [BC] The parameter $properties of Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#__construct() changed from array to a non-contravariant string
- [BC] The parameter $suppressAll of Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#__construct() changed from bool to a non-contravariant array
- [BC] The parameter $snapshotStore of Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#__construct() changed from string|null to a non-contravariant bool
- [BC] The parameter $snapshotBatch of Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#__construct() changed from int|null to a non-contravariant Patchlevel\EventSourcing\Metadata\AggregateRoot\Snapshot|null
- [BC] Default parameter value for parameter $properties of Patchlevel\EventSourcing\Metadata\Event\EventMetadata#__construct() changed from array (
  ) to false
- [BC] Default parameter value for parameter $splitStream of Patchlevel\EventSourcing\Metadata\Event\EventMetadata#__construct() changed from false to NULL
- [BC] The parameter $properties of Patchlevel\EventSourcing\Metadata\Event\EventMetadata#__construct() changed from array to a non-contravariant bool
- [BC] The parameter $splitStream of Patchlevel\EventSourcing\Metadata\Event\EventMetadata#__construct() changed from bool to a non-contravariant string|null
- [BC] The number of required arguments for Exception#__construct() increased from 0 to 1
- [BC] The parameter $aggregateId of Patchlevel\EventSourcing\Repository\SnapshotRebuildFailed#__construct() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $eventBus of Patchlevel\EventSourcing\Repository\DefaultRepository#__construct() changed from Patchlevel\EventSourcing\EventBus\EventBus to a non-contravariant Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata
- [BC] The parameter $aggregateClass of Patchlevel\EventSourcing\Repository\DefaultRepository#__construct() changed from string to a non-contravariant Patchlevel\EventSourcing\EventBus\EventBus|null
- [BC] The parameter $messageDecorator of Patchlevel\EventSourcing\Repository\DefaultRepository#__construct() changed from Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator|null to a non-contravariant Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator|null
- [BC] The parameter $logger of Patchlevel\EventSourcing\Repository\DefaultRepository#__construct() changed from Psr\Log\LoggerInterface|null to a non-contravariant Psr\Clock\ClockInterface|null
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\DefaultRepository#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\DefaultRepository#has() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\AggregateNotFound#__construct() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\Repository#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\Repository#load() changed from string to Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\Repository#has() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Repository\Repository#has() changed from string to Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $messageDecorator of Patchlevel\EventSourcing\Repository\DefaultRepositoryManager#__construct() changed from Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator|null to a non-contravariant Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator|null
- [BC] The parameter $logger of Patchlevel\EventSourcing\Repository\DefaultRepositoryManager#__construct() changed from Psr\Log\LoggerInterface|null to a non-contravariant Psr\Clock\ClockInterface|null
- [BC] The parameter $aggregateId of Patchlevel\EventSourcing\Repository\PlayheadMismatch#__construct() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] Default parameter value for parameter $fromPlayhead of Patchlevel\EventSourcing\Store\Store#load() changed from 0 to NULL
- [BC] The return type of Patchlevel\EventSourcing\Store\Store#load() changed from array to the non-covariant Patchlevel\EventSourcing\Store\Stream
- [BC] The return type of Patchlevel\EventSourcing\Store\Store#load() changed from array to Patchlevel\EventSourcing\Store\Stream
- [BC] The parameter $aggregate of Patchlevel\EventSourcing\Store\Store#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Store\Criteria|null
- [BC] The parameter $id of Patchlevel\EventSourcing\Store\Store#load() changed from string to a non-contravariant int|null
- [BC] The parameter $aggregate of Patchlevel\EventSourcing\Store\Store#load() changed from string to Patchlevel\EventSourcing\Store\Criteria|null
- [BC] The parameter $id of Patchlevel\EventSourcing\Store\Store#load() changed from string to int|null
- [BC] The parameter $fromPlayhead of Patchlevel\EventSourcing\Store\Store#load() changed from int to int|null
- [BC] Parameter 0 of Patchlevel\EventSourcing\Store\Store#load() changed name from aggregate to criteria
- [BC] Parameter 1 of Patchlevel\EventSourcing\Store\Store#load() changed name from id to limit
- [BC] Parameter 2 of Patchlevel\EventSourcing\Store\Store#load() changed name from fromPlayhead to offset
- [BC] The parameter $messages of Patchlevel\EventSourcing\Store\Store#save() changed from Patchlevel\EventSourcing\EventBus\Message to a non-contravariant Patchlevel\EventSourcing\Message\Message
- [BC] The parameter $messages of Patchlevel\EventSourcing\Store\Store#save() changed from Patchlevel\EventSourcing\EventBus\Message to Patchlevel\EventSourcing\Message\Message
- [BC] The parameter $schemaConfigurator of Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber#__construct() changed from Patchlevel\EventSourcing\Schema\SchemaConfigurator to a non-contravariant Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator
- [BC] The parameter $schemaConfigurator of Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector#__construct() changed from Patchlevel\EventSourcing\Schema\SchemaConfigurator to a non-contravariant Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator
- [BC] The number of required arguments for Patchlevel\EventSourcing\EventBus\DefaultEventBus#__construct() increased from 0 to 1
- [BC] The parameter $listeners of Patchlevel\EventSourcing\EventBus\DefaultEventBus#__construct() changed from array to a non-contravariant Patchlevel\EventSourcing\EventBus\Consumer
- [BC] The parameter $messages of Patchlevel\EventSourcing\EventBus\DefaultEventBus#dispatch() changed from Patchlevel\EventSourcing\EventBus\Message to a non-contravariant Patchlevel\EventSourcing\Message\Message
- [BC] The parameter $messages of Patchlevel\EventSourcing\EventBus\EventBus#dispatch() changed from Patchlevel\EventSourcing\EventBus\Message to a non-contravariant Patchlevel\EventSourcing\Message\Message
- [BC] The parameter $messages of Patchlevel\EventSourcing\EventBus\EventBus#dispatch() changed from Patchlevel\EventSourcing\EventBus\Message to Patchlevel\EventSourcing\Message\Message
- [BC] The parameter $hydrator of Patchlevel\EventSourcing\Serializer\DefaultEventSerializer#__construct() changed from Patchlevel\EventSourcing\Serializer\Hydrator\EventHydrator to a non-contravariant Patchlevel\Hydrator\Hydrator
- [BC] The parameter $store of Patchlevel\EventSourcing\Console\Command\DatabaseCreateCommand#__construct() changed from Patchlevel\EventSourcing\Store\Store to a non-contravariant Doctrine\DBAL\Connection
- [BC] The number of required arguments for Patchlevel\EventSourcing\Console\Command\WatchCommand#__construct() increased from 2 to 3
- [BC] The parameter $server of Patchlevel\EventSourcing\Console\Command\WatchCommand#__construct() changed from Patchlevel\EventSourcing\WatchServer\WatchServer to a non-contravariant Patchlevel\EventSourcing\Store\Store
- [BC] The parameter $aggregateRootRegistry of Patchlevel\EventSourcing\Console\Command\ShowCommand#__construct() changed from Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry to a non-contravariant Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer
- [BC] The parameter $store of Patchlevel\EventSourcing\Console\Command\SchemaDropCommand#__construct() changed from Patchlevel\EventSourcing\Store\Store to a non-contravariant Patchlevel\EventSourcing\Schema\SchemaDirector
- [BC] The parameter $store of Patchlevel\EventSourcing\Console\Command\SchemaUpdateCommand#__construct() changed from Patchlevel\EventSourcing\Store\Store to a non-contravariant Patchlevel\EventSourcing\Schema\SchemaDirector
- [BC] The parameter $store of Patchlevel\EventSourcing\Console\Command\SchemaCreateCommand#__construct() changed from Patchlevel\EventSourcing\Store\Store to a non-contravariant Patchlevel\EventSourcing\Schema\SchemaDirector
- [BC] The parameter $store of Patchlevel\EventSourcing\Console\Command\DatabaseDropCommand#__construct() changed from Patchlevel\EventSourcing\Store\Store to a non-contravariant Doctrine\DBAL\Connection
- [BC] The number of required arguments for Patchlevel\EventSourcing\Console\OutputStyle#message() increased from 2 to 3
- [BC] The parameter $message of Patchlevel\EventSourcing\Console\OutputStyle#message() changed from Patchlevel\EventSourcing\EventBus\Message to a non-contravariant Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer
- [BC] The parameter $hydrator of Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore#__construct() changed from Patchlevel\EventSourcing\Serializer\Hydrator\AggregateRootHydrator|null to a non-contravariant Patchlevel\Hydrator\Hydrator|null
- [BC] The parameter $id of Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Snapshot\SnapshotStore#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Snapshot\SnapshotStore#load() changed from string to Patchlevel\EventSourcing\Aggregate\AggregateRootId
- [BC] The parameter $id of Patchlevel\EventSourcing\Snapshot\SnapshotNotFound#__construct() changed from string to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRootId

# Removed
- [BC] Class Patchlevel\EventSourcing\WatchServer\SendingFailed has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\SocketWatchServer has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\WatchServer has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\WatchListener has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\MessageSerializer has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\SocketWatchServerClient has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\WatchServerClient has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\PhpNativeMessageSerializer has been deleted
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot#__construct() was removed
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot#apply() was removed
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot#recordThat() was removed
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot::metadata() was removed
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot::setMetadataFactory() was removed
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\DuplicateCreateMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\DuplicateDropMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\DuplicateHandleMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\AttributeProjectionMetadataFactory has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadata has been deleted
- [BC] Class Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootPropertyMetadata has been deleted
- [BC] Property Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#$properties was removed
- [BC] Property Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#$snapshotStore was removed
- [BC] Property Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#$snapshotBatch was removed
- [BC] Property Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata#$snapshotVersion was removed
- [BC] Property Patchlevel\EventSourcing\Metadata\Event\EventMetadata#$properties was removed
- [BC] Class Patchlevel\EventSourcing\Metadata\Event\EventPropertyMetadata has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Source\Source has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Source\StoreSource has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Source\InMemorySource has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\ProjectorTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\ProjectorRepositoryTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\ProjectionHandlerTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\Target has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\StoreTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Pipeline has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\OnlyArchivedEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\FilterEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\Middleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeArchivedEventMiddleware has been deleted
- [BC] Method Patchlevel\EventSourcing\Repository\SnapshotRebuildFailed#aggregateId() was removed
- [BC] Class Patchlevel\EventSourcing\Repository\InvalidAggregateClass has been deleted
- [BC] Method Patchlevel\EventSourcing\Store\Store#has() was removed
- [BC] Class Patchlevel\EventSourcing\Store\PipelineStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\StreamableStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\CorruptedMetadata has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\MultiTableStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\OutboxStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\SplitEventstreamStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\SingleTableStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\DoctrineStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Store\TransactionStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Clock\Clock has been deleted
- [BC] These ancestors of Patchlevel\EventSourcing\Clock\FrozenClock have been removed: ["Patchlevel\\EventSourcing\\Clock\\Clock"]
- [BC] These ancestors of Patchlevel\EventSourcing\Clock\SystemClock have been removed: ["Patchlevel\\EventSourcing\\Clock\\Clock"]
- [BC] Class Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\SchemaManager has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\StoreNotSupported has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\DoctrineSchemaManager has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\DryRunSchemaManager has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\MigrationSchemaProvider has been deleted
- [BC] Class Patchlevel\EventSourcing\Schema\SchemaConfigurator has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\ProjectionListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\ProjectionId has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\Projection has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\Projector has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\ProjectionHandler has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projectionist\ProjectorNotFound has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projectionist\VersionedProjector has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projectionist\Projectionist has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projectionist\RunProjectionistEventBusWrapper has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist has been deleted
- [BC] Method Patchlevel\EventSourcing\Attribute\Snapshot#name() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\Snapshot#batch() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\Snapshot#version() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\Aggregate#name() was removed
- [BC] Class Patchlevel\EventSourcing\Attribute\NormalizedName has been deleted
- [BC] Class Patchlevel\EventSourcing\Attribute\Create has been deleted
- [BC] Class Patchlevel\EventSourcing\Attribute\Normalize has been deleted
- [BC] Class Patchlevel\EventSourcing\Attribute\Handle has been deleted
- [BC] Class Patchlevel\EventSourcing\Attribute\Drop has been deleted
- [BC] Method Patchlevel\EventSourcing\Attribute\Event#name() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\SuppressMissingApply#suppressEvents() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\SuppressMissingApply#suppressAll() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\Apply#eventClass() was removed
- [BC] Class Patchlevel\EventSourcing\EventBus\SymfonyEventBus has been deleted
- [BC] Method Patchlevel\EventSourcing\EventBus\DefaultEventBus#addListener() was removed
- [BC] Class Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\DuplicateHandleMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Subscriber has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Message has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\Listener has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\HeaderNotFound has been deleted
- [BC] Class Patchlevel\EventSourcing\EventBus\EventBusException has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\MissingPlayhead has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\DenormalizationFailure has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\MetadataAggregateRootHydrator has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\HydratorException has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\TypeMismatch has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\MetadataEventHydrator has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\NormalizationFailure has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\AggregateRootHydrator has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Hydrator\EventHydrator has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeZoneNormalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\ArrayNormalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeNormalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument has been deleted
- [BC] Class Patchlevel\EventSourcing\Serializer\Normalizer\EnumNormalizer has been deleted
- [BC] Class Patchlevel\EventSourcing\Outbox\OutboxConsumer has been deleted
- [BC] Class Patchlevel\EventSourcing\Outbox\StoreOutboxConsumer has been deleted
- [BC] Class Patchlevel\EventSourcing\Outbox\OutboxEventBus has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistTeardownCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistRemoveCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\OutboxInfoCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistRunCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistReactivateCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistBootCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistStatusCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionistCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Command\OutboxConsumeCommand has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnMemoryLimitListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnIterationLimitListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnTimeLimitListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnSigtermSignalListener has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\InvalidFormat has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\DefaultWorker has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Worker has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Bytes has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Event\WorkerStoppedEvent has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent has been deleted
- [BC] Class Patchlevel\EventSourcing\Lock\DoctrineDbalStoreSchemaAdapter has been deleted
