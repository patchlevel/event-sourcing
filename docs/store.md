# Store

In the end, the messages have to be saved somewhere.
Each message contains an event and the associated headers.

:::note
More information can be found in the [message](message.md) documentation.
:::

The store is optimized to efficiently store and load events for aggregates.

## Configure Store

We offer different stores to store the messages.

### StreamDoctrineDbalStore

We offer a store called `StreamDoctrineDbalStore`.
The store needs a dbal connection, an event serializer and has some optional parameters like options.

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;

$connection = DriverManager::getConnection(
    (new DsnParser())->parse('pdo-pgsql://user:secret@localhost/app'),
);

$store = new StreamDoctrineDbalStore(
    $connection,
    DefaultEventSerializer::createFromPaths(['src/Event']),
);
```
:::note
You can find out more about [how to create a connection](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
in the doctrine dbal documentation.
:::

Following options are available in `StreamDoctrineDbalStore`:

| Option       | Type   | Default     | Description                                   |
|--------------|--------|-------------|-----------------------------------------------|
| table_name   | string | event_store | The name of the table in the database         |
| locking      | bool   | true        | If the store should use locking for writing   |
| lock_id      | int    | 133742      | The id of the lock                            |
| lock_timeout | int    | -1          | The timeout of the lock. -1 means no timeout  |
| keep_index   | bool   | false       | If enabled, the index header is kept on save  |

The table structure of the `StreamDoctrineDbalStore` looks like this:

| Column           | Type     | Description                                      |
|------------------|----------|--------------------------------------------------|
| id               | bigint   | The index of the whole stream (autoincrement)    |
| stream           | string   | The name of the stream                           |
| playhead         | ?int     | The current playhead of the aggregate            |
| event_id         | string   | The id of the event                              |
| event_name       | string   | The name of the event                            |
| event_payload    | json     | The payload of the event                         |
| recorded_on      | datetime | The date when the event was recorded             |
| new_stream_start | bool     | If the event is the first event of the aggregate |
| archived         | bool     | If the event is archived                         |
| custom_headers   | json     | Custom headers for the event                     |

### TaggableDoctrineDbalStore

The `TaggableDoctrineDbalStore` works like the `StreamDoctrineDbalStore`, but additionally stores the
event tags in a dedicated `tags` column and can query events by tag and append events with an
optimistic condition. This is the store used by the
[dynamic consistency boundary](dynamic-consistency-boundary.md).

Besides the dbal connection and the event serializer, it also needs the event registry so it can
resolve tags and event names.

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;

$connection = DriverManager::getConnection(
    (new DsnParser())->parse('pdo-pgsql://user:secret@localhost/app'),
);

$eventRegistry = (new AttributeEventRegistryFactory())->create(['src/Event']);
$serializer = new DefaultEventSerializer($eventRegistry);

$store = new TaggableDoctrineDbalStore(
    $connection,
    $serializer,
    $eventRegistry,
);
```
:::experimental
This feature is still experimental and may change in the future.
Use it with caution.
:::

Following options are available in `TaggableDoctrineDbalStore`:

| Option              | Type   | Default     | Description                                          |
|---------------------|--------|-------------|-----------------------------------------------------|
| table_name          | string | event_store | The name of the table in the database               |
| locking             | bool   | true        | If the store should use locking for writing         |
| lock_id             | int    | 133742      | The id of the lock                                  |
| lock_timeout        | int    | -1          | The timeout of the lock. -1 means no timeout        |
| keep_index          | bool   | false       | If enabled, the index header is kept on save        |
| default_stream_name | string | main        | Stream name used when a message has no stream header |

The table structure of the `TaggableDoctrineDbalStore` looks like this:

| Column         | Type     | Description                                   |
|----------------|----------|----------------------------------------------|
| id             | bigint   | The index of the whole stream (autoincrement) |
| stream         | string   | The name of the stream                        |
| playhead       | ?int     | The current playhead of the aggregate         |
| event_id       | string   | The id of the event                           |
| event_name     | string   | The name of the event                         |
| event_payload  | json     | The payload of the event                      |
| recorded_on    | datetime | The date when the event was recorded          |
| archived       | bool     | If the event is archived                      |
| tags           | ?json    | The tags attached to the event                |
| custom_headers | json     | Custom headers for the event                  |

### InMemoryStore

We also offer an in-memory store for testing purposes.

```php
use Patchlevel\EventSourcing\Store\InMemoryStore;

$store = new InMemoryStore();
```
:::tip
You can pass messages to the constructor to initialize the store with some events.
:::

The `InMemoryStore` also implements the tag-based query and conditional append API, so it can
back a [dynamic consistency boundary](dynamic-consistency-boundary.md) decision model in tests
without a real database.

### ReadOnlyStore

Last but not least, we offer a read-only store named `ReadOnlyStore`.
It passes all methods to the underlying store, but throws a `StoreIsReadOnly` exception when trying to execute write
operations.

```php
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$readOnlyStore = new ReadOnlyStore($store);
```
## Schema

With the help of the `SchemaDirector`, the database structure can be created, updated and deleted.

:::tip
You can also use doctrine migration to create and keep your schema in sync.
:::

### Doctrine Schema Director

The `SchemaDirector` is responsible for creating, updating and deleting the database schema.
The `DoctrineSchemaDirector` is a concrete implementation of the `SchemaDirector` for doctrine dbal.
Additionally, it implements the `DryRunSchemaDirector` interface, to show the sql statements that would be executed.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var Connection $connection
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store,
);
```
:::note
How to setup [cli commands](cli.md) for the schema director is described in the CLI documentation.
:::

#### Create schema

You can create the table from scratch using the `create` method.

```php
use Patchlevel\EventSourcing\Schema\SchemaDirector;

/** @var SchemaDirector $schemaDirector */
$schemaDirector->create();
```
Or can give you back which SQL statements would be necessary for this.
Either for a dry run, or to define your own migrations.

```php
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;

/** @var DryRunSchemaDirector $schemaDirector */
$sql = $schemaDirector->dryRunCreate();
```
#### Update schema

The update method compares the current state in the database and how the table should be structured.
As a result, the diff is executed to bring the table to the desired state.

```php
use Patchlevel\EventSourcing\Schema\SchemaDirector;

/** @var SchemaDirector $schemaDirector */
$schemaDirector->update();
```
Or can give you back which SQL statements would be necessary for this.

```php
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;

/** @var DryRunSchemaDirector $schemaDirector */
$sql = $schemaDirector->dryRunUpdate();
```
#### Drop schema

You can also delete the table with the `drop` method.

```php
use Patchlevel\EventSourcing\Schema\SchemaDirector;

/** @var SchemaDirector $schemaDirector */
$schemaDirector->drop();
```
Or can give you back which SQL statements would be necessary for this.

```php
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;

/** @var DryRunSchemaDirector $schemaDirector */
$sql = $schemaDirector->dryRunDrop();
```
### Doctrine Migrations

You can use [doctrine migration](https://www.doctrine-project.org/projects/migrations.html),
which is known from [doctrine orm](https://www.doctrine-project.org/projects/orm.html),
to create your schema and keep it in sync.
We have added a `DoctrineMigrationSchemaProvider` for doctrine migrations so that you just have to plug the whole thing
together.

```php
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\Store;

// event sourcing schema director configuration

/**
 * @var Connection $connection
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store,
);

$schemaProvider = new DoctrineMigrationSchemaProvider($schemaDirector);

// doctrine migration configuration

/** @var ConfigurationLoader $configLoader */
$dependencyFactory = DependencyFactory::fromConnection(
    $configLoader,
    new ExistingConnection($connection),
);

$dependencyFactory->setService(
    SchemaProvider::class,
    $schemaProvider,
);
```
:::note
Here you can find more information on how to
[configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).
:::

:::note
How to setup [cli commands](cli.md) for doctrine migrations is described in the CLI documentation.
:::

## Usage

The store has a few methods to interact with the database.

### Load

You can load all events from an aggregate with the `load` method.
This method returns a `Stream` object, which is a collection of events.

```php
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$stream = $store->load();
```
The load method also has a few parameters to filter, limit and sort the events.

```php
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$stream = $store->load(
    new Criteria(), // filter criteria
    100, // limit
    50, // offset
    true,  // latest first
);
```
#### Criteria

The `Criteria` object is used to filter the events.

```php
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;

$criteria = new Criteria(
    new StreamCriterion('profile-e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e'),
    new FromPlayheadCriterion(2),
    new FromIndexCriterion(100),
    new ToIndexCriterion(200),
    new ArchivedCriterion(true),
    new EventsCriterion(['profile.created', 'profile.name_changed']),
);
```
The `StreamCriterion` is variadic, so you can pass multiple stream names.
There is also a `startWith` named constructor that appends the wildcard for you.

```php
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;

$criterion = new StreamCriterion('profile-*', 'hotel-*');
$criterion = StreamCriterion::startWith('profile-');
```
Or you can the criteria builder to create the criteria.

```php
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;

$criteria = (new CriteriaBuilder())
    ->streamName('profile-e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e')
    ->fromPlayhead(2)
    ->fromIndex(100)
    ->archived(true)
    ->events(['profile.created', 'profile.name_changed'])
    ->build();
```
:::tip
A stream name has the format `[aggregateName]-[aggregateId]`. To match every stream of an aggregate,
use a wildcard with `StreamCriterion::startWith('profile-')`.
:::

#### Stream

The load method returns a `Stream` object and is a generator.
This means that the messages are only loaded when they are needed.

```php
use Patchlevel\EventSourcing\Message\Stream;

/** @var Stream $stream */
$stream->index(); // get the index of the stream
$stream->position(); // get the current position of the stream
$stream->current(); // get the current event
$stream->next(); // move to the next event
$stream->end(); // check if the stream is at the end

foreach ($stream as $message) {
    $message->event(); // get the event
}
```
:::note
You can find more information about the [`Message` object](message.md).
:::

:::warning
The stream cannot rewind, so you can only iterate over it once.
If you want to iterate over it again, you have to call the `load` method again.
:::

### Count

You can count the number of events in the store with the `count` method.

```php
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$count = $store->count();
```
The count method also has the possibility to filter the events.

```php
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$count = $store->count(
    new Criteria(), // filter criteria
);
```
### Save

You can save a message with the `save` method.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var Store $store
 * @var Message $message
 * @var Message $message1
 * @var Message $message2
 * @var Message $message3
 * @var list<Message> $messages
 */
$store->save($message);
$store->save($message1, $message2, $message3);
$store->save(...$messages);
```
:::note
The saving happens in a transaction, so all messages are saved or none.
The store locks the table for writing during each save by default.
:::

:::tip
Use the transactional method if you want to call multiple save methods in one transaction.
:::

### Update

It is not possible to update events.
In event sourcing, the events are immutable.

### Remove

You can remove events with the `remove` method. It takes the same criteria as the `load` method.

```php
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$store->remove(new Criteria(StreamCriterion::startWith('profile-')));
```
:::danger
Without criteria the method removes every event in the store.
Deleted events cannot be restored, all subscriptions built from them become inconsistent.
:::

### Archive

You can archive events with the `archive` method.
Archived events are still in the store, but they are skipped when an aggregate is loaded,
which keeps the loading of long living aggregates fast.

```php
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$store->archive(
    new Criteria(
        new StreamCriterion('profile-e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e'),
        new ToPlayheadCriterion(100),
    ),
);
```
Archived events get the `ArchivedHeader` when they are loaded again.
You can include or exclude them explicitly with the `ArchivedCriterion`.

```php
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$stream = $store->load(new Criteria(new ArchivedCriterion(false)));
```
:::note
This method is used when a [split stream](split-stream.md) event is saved.
:::

:::tip
Archiving is the non destructive alternative to `remove`. The events stay readable,
so you can still replay them by passing an `ArchivedCriterion(true)`.
:::

### List Streams

You can list all streams with the `streams` method.

```php
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$streams = $store->streams(); // ['profile-1', 'profile-2', 'profile-3']
```
### Transaction

There is also the possibility of executing a function in a transaction.
The store takes care of starting a transaction, committing it and then possibly rollback it again.

```php
use Patchlevel\EventSourcing\Store\Store;

/** @var Store $store */
$store->transactional(static function () use ($command, $bankAccountRepository): void {
    $accountFrom = $bankAccountRepository->get($command->from());
    $accountTo = $bankAccountRepository->get($command->to());

    $accountFrom->transferMoney($command->to(), $command->amount());
    $accountTo->receiveMoney($command->from(), $command->amount());

    $bankAccountRepository->save($accountFrom);
    $bankAccountRepository->save($accountTo);
});
```
:::note
The store locks the table for writing during the transaction by default.
:::

:::tip
If you only want to save one aggregate, you don't have to use the transactional method.
The save method in store and repository is already transactional.
:::

## Learn more

* [How to create events](events.md)
* [How to use repositories](repository.md)
* [How to create messages](message.md)
* [How to create projections](subscription.md)
* [How to upcast events](upcasting.md)
* [How to configure cli commands](cli.md)
