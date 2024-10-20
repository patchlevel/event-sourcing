# Upgrade from v2 to v3

We are only providing here the most important changes in detail. For the full BC-Break list go to
the [full BC-Break list](#full-bc-break-list)

## Detailed change list

### Outsourced packages

These packages lived in this library before and are now outsourced to be more flexible in releasing them on their own.
This can be done because the domain of this packages are not bound to event sourcing itself and instead provide
functionality which can be used in other projects as well.

1. The `Worker` is now an extra package [patchlevel/worker](https://github.com/patchlevel/worker).
2. The `Hydrator` is now an extra package [patchlevel/hydrator](https://github.com/patchlevel/hydrator).

### New packages

We will also introduce [a new bundle](https://github.com/patchlevel/event-sourcing-admin-bundle) coming with version 3.
This bundle aims to create an even better developer experience by providing a nice view of all the aspects of the event
source application.

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
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}

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

#### Normalizer

The Attribute `#[Normalize]` was removed, use the Normalizer itself instead e.g. `#[IdNormalizer]`. Most of the
Normalizers are now located in [patchlevel/hydrator](https://github.com/patchlevel/hydrator).
Your own normalizer needs to add the `#[Attribute(Attribute::TARGET_PROPERTY)]` attribute to the class.

#### Subscription

In 2.1.0 we introduced the `Projectionist` and after that the continued on working on this system. With 3.0 we are
delivering alot of more feature to this system and it got a complete overhaul as an renaming. It's now called
`Subscription`. With this we are pushing our event based system mostly complete asynchronous. We are still offering a
"sync mode" mostly for testing purposes. For more information about this have a look in our
[docs](https://patchlevel.github.io/event-sourcing-docs/3.0/subscription/).

#### EventBus

We made the EventBus now completely optional but we still provide a default implementation and also a PSR-14 Adapter.
Also the Symfony Messenger implementation was moved to the [bundle](https://github.com/patchlevel/event-sourcing-bundle).

We encourage to use our Subscription system for most of the use cases.

#### Outbox

Our outbox implementation relied on the eventbus and was a solution to the eventual consistency problem. We think that
we have now a better solution for that with our Subscription feature. If you still want an outbox table because you have
internally process running on this table then your can replicate the behaviour with an `#[Projector]`.

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;

#[Processor('outbox')]
class OutboxProcessor
{
    public function __construct(
        private readonly EventBus $eventBus,
    ) {
    }

    #[Subscribe('*')]
    public function publish(Message $message): void
    {
        $this->eventBus->dispatch($message);
    }
}
```
#### WatchServer

The `WatchServer` is removed, but you can still watch which events where published via the `WatchCommand` as before. It
is now internally using the `Subscription` systems to deliver this feature.

### Store

#### Multi-Table Store

We removed the `MultiTableStore` without any replacement. We did this because it does not bring any benefit for the user
besides some more confidence due to be a little more similar to the "standard" ORM based databases. For the migration
please have a look
[here to migrate from the MultiTableStore to the SingleTableStore](https://patchlevel.github.io/event-sourcing-docs/2.3/migrate-multi-table-store-to-single-table-store/).
For more convenience and overview we will provide [a bundle](https://github.com/patchlevel/event-sourcing-admin-bundle)
which will visualize all aspects of the event sourced application.

#### Misc

`TransactionalStore` was removed and got merged with `Store`. So now every `Store` needs a transactional capability.
`SplitEventstreamStore` was removed and is now part of the internal logic of the `Store`.

### Pipeline

The `Pipeline` was also removed in favor of the `Subscription` system. You can achieve the same behaviour with an
`Projector` using the `Middlewares` which are now called `MessageTranslators`.

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\Store\Store;

#[Processor('pipeline')]
class PipelineProcessor
{
    public function __construct(
        private readonly Store $store, // new eventstore
        private readonly Translator $translator,
    ) {
    }

    #[Subscribe('*')]
    public function publish(Message $message): void
    {
        $messages = ($this->translator)($message);

        $this->store->save(...$messages);
    }
}
```
### Message

The `Message` class was moved into an own namespace and some changes where made to decouple it from the EventBus.

#### Headers

We changed the interface for the Headers. Instead of just passing a pair of key value into an array we now accept
classes for that case. This classes can be marked as Headers with an Attribute `#[Header]`.

Before:

```php
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create($event);
$message = $message->customHeader('key', 'value');
```
After:

```php
use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create($event);
$message = $message->header(new CustomHeader('value'));

#[Header('key')]
class CustomHeader
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
```
### Schema

Renamed `*SchemaConfigurator` to `*DoctrineSchemaConfigurator`

### Projection & Projectionist

The `Projectionist` system was overhauled and renamed to `Subscription`. Also, it is now the  default for creating
`Projections` and `Processors`.

Some Attributes where renamed:

* `#[Handle]` was renamed to `#[Subscribe]`
* `#[Create]` was renamed to `#[Setup]`
* `#[Drop]` was renamed to `#[Teardown]`

`VersionedProjector` with the method `targetProjection` where replaced by the Attribute `#[Projector]`. The version is
now part of the name, so if you want to create a new projection version you will need to update the name from e.g.
`#[Projector('projection.user_registered']` to `#[Projector('projection.user_registered_1']`.

The logic of the method `Projectionist::boot()` was split up into 2 methods. These are `Projectionist::setup()` and
`Projectionist::boot()`.

Some classes where also renamed:

* `DefaultProjectionist` was renamed to `DefaultSubscriptionEngine`
* `DoctrineStore` was renamed to `DoctrineSubscriptionStore`
* `ProjectorRepository` was renamed to `MetadataSubscriberAccessorRepository`

And also the CLI commands where renamed accordingly:

* `event-sourcing:projectionist:boot` was renamed to `event-sourcing:subscription:boot`
* `event-sourcing:projectionist:run` was renamed to `event-sourcing:subscription:run`
* `event-sourcing:projectionist:pause` was renamed to `event-sourcing:subscription:pause`
* `event-sourcing:projectionist:reactivate` was renamed to `event-sourcing:subscription:reactivate`
* `event-sourcing:projectionist:remove` was renamed to `event-sourcing:subscription:remove`
* `event-sourcing:projectionist:status` was renamed to `event-sourcing:subscription:status`
* `event-sourcing:projectionist:teardown` was renamed to `event-sourcing:subscription:teardown`

And now there is one new cli command to reflect the new `setup` method: `event-sourcing:subscription:setup`

### Attributes

All Attributes are now using `public readonly` properties instead of using methods to retrieve the data.

Before:

```php
#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    public function __construct(
        private string $name,
        private int|null $batch = null,
        private string|null $version = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function batch(): int|null
    {
        return $this->batch;
    }

    public function version(): string|null
    {
        return $this->version;
    }
}
```
After:

```php
#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    public function __construct(
        public readonly string $name,
        public readonly int|null $batch = null,
        public readonly string|null $version = null,
    ) {
    }
}
```
### Clock

Our own interface was removed, we are using the PSR-20 interface instead.

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
- [BC] The parameter $properties of Patchlevel\EventSourcing\Metadata\Event\EventMetadata#__construct() changed from array to a non-contravariant bool
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
- [BC] The parameter $aggregate of Patchlevel\EventSourcing\Store\Store#load() changed from string to a non-contravariant Patchlevel\EventSourcing\Store\Criteria\Criteria|null
- [BC] The parameter $id of Patchlevel\EventSourcing\Store\Store#load() changed from string to a non-contravariant int|null
- [BC] The parameter $aggregate of Patchlevel\EventSourcing\Store\Store#load() changed from string to Patchlevel\EventSourcing\Store\Criteria\Criteria|null
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
- [BC] These ancestors of Patchlevel\EventSourcing\Clock\FrozenClock have been removed: ["Patchlevel\EventSourcing\Clock\Clock"]
- [BC] These ancestors of Patchlevel\EventSourcing\Clock\SystemClock have been removed: ["Patchlevel\EventSourcing\Clock\Clock"]
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
