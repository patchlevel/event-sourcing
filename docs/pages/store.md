# Store

In the end, the messages have to be saved somewhere.
Each message contains an event and the associated headers.

!!! note

    More information about the message can be found [here](message.md).
    
The store is optimized to efficiently store and load events for aggregates.

## Configure Store

We offer different stores to store the messages.
Two stores based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html)
and one in-memory store for testing purposes.

### DoctrineDbalStore

This is the current default store for event sourcing.
You can create a store with the `DoctrineDbalStore` class.
The store needs a dbal connection, an event serializer and has some optional parameters like options.

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;

$connection = DriverManager::getConnection(
    (new DsnParser())->parse('pdo-pgsql://user:secret@localhost/app'),
);

$store = new DoctrineDbalStore(
    $connection,
    DefaultEventSerializer::createFromPaths(['src/Event']),
);
```
!!! note

    You can find out more about how to create a connection 
    [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
    
Following options are available in `DoctrineDbalStore`:

| Option            | Type            | Default    | Description                                  |
|-------------------|-----------------|------------|----------------------------------------------|
| table_name        | string          | eventstore | The name of the table in the database        |
| aggregate_id_type | "uuid"/"string" | uuid       | The type of the `aggregate_id` column        |
| locking           | bool            | true       | If the store should use locking for writing  |
| lock_id           | int             | 133742     | The id of the lock                           |
| lock_timeout      | int             | -1         | The timeout of the lock. -1 means no timeout |

The table structure of the `DoctrineDbalStore` looks like this:

| Column           | Type        | Description                                      |
|------------------|-------------|--------------------------------------------------|
| id               | bigint      | The index of the whole stream (autoincrement)    |
| aggregate        | string      | The name of the aggregate                        |
| aggregate_id     | uuid/string | The id of the aggregate                          |
| playhead         | int         | The current playhead of the aggregate            |
| event            | string      | The name of the event                            |
| payload          | json        | The payload of the event                         |
| recorded_on      | datetime    | The date when the event was recorded             |
| new_stream_start | bool        | If the event is the first event of the aggregate |
| archived         | bool        | If the event is archived                         |
| custom_headers   | json        | Custom headers for the event                     |

!!! note

    The default type of the `aggregate_id` column is `uuid` if the database supports it and `string` if not.
    You can change the type with the `aggregate_id_type` to `string` if you want use custom id.
    
### StreamDoctrineDbalStore

??? example "Experimental"

    This feature is still experimental and may change in the future.
    Use it with caution.
    
We offer a new experimental store called `StreamDoctrineDbalStore`.
This store is decoupled from the aggregate and can be used to store events from other sources.
The difference to the `DoctrineDbalStore` is that the `StreamDoctrineDbalStore` merge the aggregate id
and the aggregate name into one column named `stream`. Additionally, the column `playhead` is nullable.
This store introduces two new methods `streams` and `remove`.

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
!!! note

    You can find out more about how to create a connection 
    [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
    
Following options are available in `StreamDoctrineDbalStore`:

| Option            | Type            | Default     | Description                                  |
|-------------------|-----------------|-------------|----------------------------------------------|
| table_name        | string          | event_store | The name of the table in the database        |
| locking           | bool            | true        | If the store should use locking for writing  |
| lock_id           | int             | 133742      | The id of the lock                           |
| lock_timeout      | int             | -1          | The timeout of the lock. -1 means no timeout |

The table structure of the `StreamDoctrineDbalStore` looks like this:

| Column           | Type     | Description                                      |
|------------------|----------|--------------------------------------------------|
| id               | bigint   | The index of the whole stream (autoincrement)    |
| stream           | string   | The name of the stream                           |
| playhead         | ?int     | The current playhead of the aggregate            |
| event            | string   | The name of the event                            |
| payload          | json     | The payload of the event                         |
| recorded_on      | datetime | The date when the event was recorded             |
| new_stream_start | bool     | If the event is the first event of the aggregate |
| archived         | bool     | If the event is archived                         |
| custom_headers   | json     | Custom headers for the event                     |

### InMemoryStore

We also offer an in-memory store for testing purposes.

```php
use Patchlevel\EventSourcing\Store\InMemoryStore;

$store = new InMemoryStore();
```
!!! tip

    You can pass messages to the constructor to initialize the store with some events.
    
## Schema

With the help of the `SchemaDirector`, the database structure can be created, updated and deleted.

!!! tip

    You can also use doctrine migration to create and keep your schema in sync.
    
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
!!! note

    How to setup cli commands for schema director can be found [here](cli.md).
    
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
!!! note

    Here you can find more information on how to 
    [configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).
    
!!! note

    How to setup cli commands for doctrine migration can be found [here](cli.md).
    
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
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;

$criteria = new Criteria(
    new AggregateNameCriterion('profile'),
    new AggregateIdCriterion('e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e'),
    new FromPlayheadCriterion(2),
    new FromIndexCriterion(100),
    new ArchivedCriterion(true),
);
```
Or you can the criteria builder to create the criteria.

```php
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;

$criteria = (new CriteriaBuilder())
    ->aggregateName('profile')
    ->aggregateId('e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e')
    ->fromPlayhead(2)
    ->fromIndex(100)
    ->archived(true)
    ->build();
```
#### Stream

The load method returns a `Stream` object and is a generator.
This means that the messages are only loaded when they are needed.

```php
use Patchlevel\EventSourcing\Store\Stream;

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
!!! note

    You can find more information about the `Message` object [here](message.md).
    
!!! warning

    The stream cannot rewind, so you can only iterate over it once.
    If you want to iterate over it again, you have to call the `load` method again.
    
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
!!! note

    The saving happens in a transaction, so all messages are saved or none.    
    The store lock the table for writing during each save by default.
    
!!! tip

    Use transactional method if you want call multiple save methods in a transaction.
    
### Update

It is not possible to update events.
In event sourcing, the events are immutable.

### Remove

You can remove a stream with the `remove` method.

```php
use Patchlevel\EventSourcing\Store\StreamStore;

/** @var StreamStore $store */
$store->remove('profile-*');
```
!!! note

    The method is only available in the `StreamStore` like `StreamDoctrineDbalStore`.
    
### List Streams

You can list all streams with the `streams` method.

```php
use Patchlevel\EventSourcing\Store\StreamStore;

/** @var StreamStore $store */
$streams = $store->streams(); // ['profile-1', 'profile-2', 'profile-3']
```
!!! note

    The method is only available in the `StreamStore` like `StreamDoctrineDbalStore`.
    
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
!!! note

    The store lock the table for writing during the transaction by default.
    
!!! tip

    If you want save only one aggregate, so you don't have to use the transactional method.
    The save method in store/repository is already transactional.
    
## Learn more

* [How to create events](events.md)
* [How to use repositories](repository.md)
* [How to create message](message.md)
* [How to create projections](subscription.md)
* [How to upcast events](upcasting.md)
* [How configure cli commands](cli.md)
