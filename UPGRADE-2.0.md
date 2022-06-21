# Upgrading to v2

We are only providing here the most important changes in detail. For the full BC-Break list go to
the [full BC-Break list](#Full-BC-Break-list)

## Detailed change list

### Events

* Removed `AggregateChanged` abstract class. Use `#[Event('eventName')]` instead.

Before:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id, string $name): static 
    {
        return new static($id, ['profileId' => $id, 'name' => $name]);
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }

    public function name(): string
    {
        return $this->payload['name'];
    }
}
```

After:

```php
#[Event('profile.created')]
final class ProfileCreated 
{
    public function __construct(
        public readonly string $profileId, 
        public readonly string $name
    ) 
    {
    }
}
```

* Added `#[Normalize(YourNormalizer::class)]` for more complicated properties like ValueObjects.
* Added `#[NormalizedName('foo')]`.

```php
use YourApp\EmailNormalizer;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('profile.created')]
final class ProfileCreated 
{
    public function __construct(
        public readonly string $profileId, 
        public readonly string $name,
        #[Normalize(EmailNormalizer::class)]
        public readonly Email $email,
    ) 
    {
    }
}
```

### Aggregates

* Aggregates now needs an attribute `#[Aggregate('name')]` with a unique name.
* Removed `AttributeApplyMethod` trait. Is default behaviour now.
* Removed `StrictApplyMethod` trait. Is default behaviour now.
* Removed `NonStrictApplyMethod` trait. Use `#[SuppressMissingApply]` instead.
* Rename method `record` to `recordThat`.

### Schema

* Changed database column from `aggregateId` to `aggregate_id`. 
* Changed database column from `recordedOn` to `recorded_on`.
* Changed value of database column `event` from FQCN of the event to the name provided via the attribute.

Update the two columns, for single table store:

```SQL
ALTER TABLE eventstore RENAME COLUMN aggregateId TO aggregate_id;
ALTER TABLE eventstore RENAME COLUMN recordedOn TO recorded_on;
```

For the event name change we recommend using the upcasting feature like this:

```php
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;

final class LegacyEventNameUpaster implements Upcaster
{
    public function __construct(private readonly EventRegistry $eventRegistry){}
    
    public function __invoke(Upcast $upcast) : Upcast
    {
        return new Upcast($this->eventRegistry->eventName($upcast->eventName), $upcast->payload);
    }
}
```

If you dont want to upcast everytime you could also use the pipeline to recreate the event-stream with the fixed event
name. See for that the documentation.

### Store & Pipeline

* Renamed and adjusted parameters for `save` at `Store`
* Removed `saveBatch` at `Store`. Use new `save` instead.
* Renamed and adjusted parameters for `save` at `PipelineStore`
* Removed `saveBatch` at `PipelineStore`. Use new `save` instead.
* Changed parameter for `Middleware::__invoke` from `EventBucket` to `Message`.
* Removed `EventBucket`. `Message` is kinda a replacement.
* Removed `ClassRenameMiddleware`. This is not needed anymore since the event name is saved instead.
* Removed `FromIndexEventMiddleware`.

### Projection

* Removed `create` method from `Projection` interface. Use `#[Create]` instead.
* Removed `drop` method from `Projection` interface. Use `#[Drop]` instead.
* Removed `handledEvents` method from `Projection` interface. Use `#[Handle]` instead.
* Rename `ProjectionRepository` to `ProjectionHandler`.
* Rename `DefaultProjectionRepistory` to `DefaultProjectionHandler`.

### EventBus

* Changed `DefaultEventBus::dispatch` to accept multiple `Message` objects instead of one `AggregateChanged`.

### Snapshot

* Removed `SnapshotableAggregateRoot` abstract class. Use `#[Snapshot]` instead.

### Clock

* Removed `Clock` singleton class. See `SystemClock`, `FrozenClock` as an implementation of the `Clock` interface
  instead.

## Full BC-Break list

#### Added

- [BC] Method save() was added to interface Patchlevel\EventSourcing\Store\PipelineStore
- [BC] Method save() was added to interface Patchlevel\EventSourcing\Store\Store

#### Changed

- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\EventBus#dispatch() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\EventBus#dispatch() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to Patchlevel\EventSourcing\EventBus\Message
- [BC] Parameter 0 of Patchlevel\EventSourcing\EventBus\EventBus#dispatch() changed name from event to messages
- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\Listener#__invoke() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\Listener#__invoke() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to Patchlevel\EventSourcing\EventBus\Message
- [BC] Parameter 0 of Patchlevel\EventSourcing\EventBus\Listener#__invoke() changed name from event to message
- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\SymfonyEventBus#dispatch() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $event of Patchlevel\EventSourcing\EventBus\DefaultEventBus#dispatch() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $event of Patchlevel\EventSourcing\WatchServer\WatchServerClient#send() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $event of Patchlevel\EventSourcing\WatchServer\WatchServerClient#send() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to Patchlevel\EventSourcing\EventBus\Message
- [BC] Parameter 0 of Patchlevel\EventSourcing\WatchServer\WatchServerClient#send() changed name from event to message
- [BC] The parameter $event of Patchlevel\EventSourcing\WatchServer\WatchListener#__invoke() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $snapshot of Patchlevel\EventSourcing\Snapshot\SnapshotStore#save() changed from
  Patchlevel\EventSourcing\Snapshot\Snapshot to a non-contravariant Patchlevel\EventSourcing\Aggregate\AggregateRoot
- [BC] The parameter $snapshot of Patchlevel\EventSourcing\Snapshot\SnapshotStore#save() changed from
  Patchlevel\EventSourcing\Snapshot\Snapshot to Patchlevel\EventSourcing\Aggregate\AggregateRoot
- [BC] Parameter 0 of Patchlevel\EventSourcing\Snapshot\SnapshotStore#save() changed name from snapshot to aggregateRoot
- [BC] The return type of Patchlevel\EventSourcing\Snapshot\SnapshotStore#load() changed from
  Patchlevel\EventSourcing\Snapshot\Snapshot to the non-covariant Patchlevel\EventSourcing\Aggregate\AggregateRoot
- [BC] The return type of Patchlevel\EventSourcing\Snapshot\SnapshotStore#load() changed from
  Patchlevel\EventSourcing\Snapshot\Snapshot to Patchlevel\EventSourcing\Aggregate\AggregateRoot
- [BC] Parameter 0 of Patchlevel\EventSourcing\Snapshot\SnapshotStore#load() changed name from aggregate to
  aggregateClass
- [BC] Class Patchlevel\EventSourcing\Attribute\Apply became final
- [BC] The parameter $aggregateChangedClass of Patchlevel\EventSourcing\Attribute\Apply#__construct() changed from
  string to string|null
- [BC] Parameter 0 of Patchlevel\EventSourcing\Attribute\Apply#__construct() changed name from aggregateChangedClass to
  eventClass
- [BC] Class Patchlevel\EventSourcing\Attribute\SuppressMissingApply became final
- [BC] Class Patchlevel\EventSourcing\Attribute\Handle became final
- [BC] Parameter 0 of Patchlevel\EventSourcing\Attribute\Handle#__construct() changed name from aggregateChangedClass to
  eventClass
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Target\Target#save() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Target\Target#save() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to Patchlevel\EventSourcing\EventBus\Message
- [BC] Parameter 0 of Patchlevel\EventSourcing\Pipeline\Target\Target#save() changed name from bucket to message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget#save() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Target\StoreTarget#save() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget#save() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware#__invoke() changed
  from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware#__invoke() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware#__invoke() changed
  from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware#__invoke()
  changed from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant
  Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\Middleware#__invoke() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\Middleware#__invoke() changed from
  Patchlevel\EventSourcing\Pipeline\EventBucket to Patchlevel\EventSourcing\EventBus\Message
- [BC] Parameter 0 of Patchlevel\EventSourcing\Pipeline\Middleware\Middleware#__invoke() changed name from bucket to
  message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware#__invoke() changed
  from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware#__invoke() changed
  from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $bucket of Patchlevel\EventSourcing\Pipeline\Middleware\FilterEventMiddleware#__invoke() changed
  from Patchlevel\EventSourcing\Pipeline\EventBucket to a non-contravariant Patchlevel\EventSourcing\EventBus\Message
- [BC] The parameter $aggregate of Patchlevel\EventSourcing\Aggregate\ApplyMethodNotFound#__construct() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateRoot to a non-contravariant string
- [BC] The parameter $event of Patchlevel\EventSourcing\Aggregate\ApplyMethodNotFound#__construct() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant string
- [BC] The parameter $event of Patchlevel\EventSourcing\Aggregate\AggregateRoot#apply() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to object
- [BC] The number of required arguments for Patchlevel\EventSourcing\Store\DoctrineStore#__construct() increased from 1
  to 3
- [BC] Parameter 0 of Patchlevel\EventSourcing\Store\DoctrineStore#__construct() changed name from eventConnection to
  connection
- [BC] The parameter $aggregates of Patchlevel\EventSourcing\Store\SingleTableStore#__construct() changed from array to
  a non-contravariant Patchlevel\EventSourcing\Serializer\EventSerializer
- [BC] The parameter $storeTableName of Patchlevel\EventSourcing\Store\SingleTableStore#__construct() changed from
  string to a non-contravariant Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry
- [BC] The number of required arguments for Patchlevel\EventSourcing\Store\MultiTableStore#__construct() increased from
  2 to 3
- [BC] The parameter $aggregates of Patchlevel\EventSourcing\Store\MultiTableStore#__construct() changed from array to a
  non-contravariant Patchlevel\EventSourcing\Serializer\EventSerializer
- [BC] The parameter $metadataTableName of Patchlevel\EventSourcing\Store\MultiTableStore#__construct() changed from
  string to a non-contravariant Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $projectionRepository of Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand#__
  construct() changed from Patchlevel\EventSourcing\Projection\ProjectionRepository to a non-contravariant
  Patchlevel\EventSourcing\Projection\ProjectionHandler
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The number of required arguments for Patchlevel\EventSourcing\Console\Command\WatchCommand#__construct()
  increased from 1 to 2
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $projectionRepository of Patchlevel\EventSourcing\Console\Command\ProjectionCreateCommand#__
  construct() changed from Patchlevel\EventSourcing\Projection\ProjectionRepository to a non-contravariant
  Patchlevel\EventSourcing\Projection\ProjectionHandler
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The number of required arguments for Patchlevel\EventSourcing\Console\Command\ShowCommand#__construct() increased
  from 2 to 3
- [BC] The parameter $aggregates of Patchlevel\EventSourcing\Console\Command\ShowCommand#__construct() changed from
  array to a non-contravariant Patchlevel\EventSourcing\Serializer\EventSerializer
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $projectionRepository of Patchlevel\EventSourcing\Console\Command\ProjectionDropCommand#__
  construct() changed from Patchlevel\EventSourcing\Projection\ProjectionRepository to a non-contravariant
  Patchlevel\EventSourcing\Projection\ProjectionHandler
- [BC] The parameter $definition of Symfony\Component\Console\Command\Command#setDefinition() changed from no type to a
  non-contravariant array|Symfony\Component\Console\Input\InputDefinition
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addArgument() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $shortcut of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant string|array|null
- [BC] The parameter $default of Symfony\Component\Console\Command\Command#addOption() changed from no type to a
  non-contravariant mixed|null
- [BC] The parameter $repository of Patchlevel\EventSourcing\Projection\ProjectionListener#__construct() changed from
  Patchlevel\EventSourcing\Projection\ProjectionRepository to a non-contravariant
  Patchlevel\EventSourcing\Projection\ProjectionHandler
- [BC] The parameter $event of Patchlevel\EventSourcing\Projection\ProjectionListener#__invoke() changed from
  Patchlevel\EventSourcing\Aggregate\AggregateChanged to a non-contravariant Patchlevel\EventSourcing\EventBus\Message

#### Removed

- [BC] Class Patchlevel\EventSourcing\Clock has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\DefaultWatchServerClient has been deleted
- [BC] Class Patchlevel\EventSourcing\WatchServer\DefaultWatchServer has been deleted
- [BC] Class Patchlevel\EventSourcing\Repository\SnapshotRepository has been deleted
- [BC] Class Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Snapshot\BatchSnapshotStore has been deleted
- [BC] Class Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore has been deleted
- [BC] Method Patchlevel\EventSourcing\Attribute\Apply#aggregateChangedClass() was removed
- [BC] Method Patchlevel\EventSourcing\Attribute\Handle#aggregateChangedClass() was removed
- [BC] Class Patchlevel\EventSourcing\Pipeline\Target\ProjectionRepositoryTarget has been deleted
- [BC] Method Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget#buckets() was removed
- [BC] Class Patchlevel\EventSourcing\Pipeline\EventBucket has been deleted
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware has been deleted
- [BC] Method Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware#__construct() was removed
- [BC] Class Patchlevel\EventSourcing\Pipeline\Middleware\FromIndexEventMiddleware has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\StrictApplyMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\AttributeApplyMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\AggregateChangeRecordedAlready has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\DuplicateApplyMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\AggregateChanged has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\ClockRecordDate has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\NonStrictApplyMethod has been deleted
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot#record() was removed
- [BC] Method Patchlevel\EventSourcing\Aggregate\AggregateRoot::createFromEventStream() was removed
- [BC] Class Patchlevel\EventSourcing\Aggregate\AggregateChangeNotRecorded has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\PlayheadSequenceMismatch has been deleted
- [BC] Class Patchlevel\EventSourcing\Aggregate\ApplyAttributeNotFound has been deleted
- [BC] Method Patchlevel\EventSourcing\Store\PipelineStore#saveEventBucket() was removed
- [BC] Method Patchlevel\EventSourcing\Store\Store#saveBatch() was removed
- [BC] Method Patchlevel\EventSourcing\Store\DoctrineStore::normalizeResult() was removed
- [BC] Method Patchlevel\EventSourcing\Store\Store#saveBatch() was removed
- [BC] Class Patchlevel\EventSourcing\Store\AggregateIdMismatch has been deleted
- [BC] Method Patchlevel\EventSourcing\Store\Store#saveBatch() was removed
- [BC] Method Patchlevel\EventSourcing\Store\SingleTableStore#saveBatch() was removed
- [BC] Method Patchlevel\EventSourcing\Store\SingleTableStore#saveEventBucket() was removed
- [BC] Method Patchlevel\EventSourcing\Store\DoctrineStore::normalizeResult() was removed
- [BC] Method Patchlevel\EventSourcing\Store\MultiTableStore#saveBatch() was removed
- [BC] Method Patchlevel\EventSourcing\Store\MultiTableStore#saveEventBucket() was removed
- [BC] Method Patchlevel\EventSourcing\Store\DoctrineStore::normalizeResult() was removed
- [BC] Class Patchlevel\EventSourcing\Store\AggregateNotDefined has been deleted
- [BC] Class Patchlevel\EventSourcing\Console\EventPrinter has been deleted
- [BC] Method Patchlevel\EventSourcing\Projection\Projection#handledEvents() was removed
- [BC] Method Patchlevel\EventSourcing\Projection\Projection#create() was removed
- [BC] Method Patchlevel\EventSourcing\Projection\Projection#drop() was removed
- [BC] Class Patchlevel\EventSourcing\Projection\AttributeHandleMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\MethodDoesNotExist has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\ProjectionRepository has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\DefaultProjectionRepository has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\DuplicateHandleMethod has been deleted
- [BC] Class Patchlevel\EventSourcing\Projection\ProjectionException has been deleted
