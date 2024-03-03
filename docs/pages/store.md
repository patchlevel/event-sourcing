# Store

In the end, the messages have to be saved somewhere.
Each message contains an event and the associated headers.

!!! note

    More information about the message can be found [here](event_bus.md).

The store is optimized to efficiently store and load events for aggregates.
We currently only offer one [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html) store.

## Create DBAL connection

The first thing we need for our store is a DBAL connection:

```php
use Doctrine\DBAL\DriverManager;

$connection = DriverManager::getConnection([
    'url' => 'mysql://user:secret@localhost/app'
]);
```

!!! note

    You can find out more about how to create a connection 
    [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)

## Configure Store

You can create a store with the `DoctrineDbalStore` class.
The store needs a dbal connection, an event serializer, an aggregate registry and a table name.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;

$store = new DoctrineDbalStore(
    $connection,
    DefaultEventSerializer::createFromPaths(['src/Event']),
    new AggregateRootRegistry([
        'profile' => Profile::class
    ]),
    'eventstore'
);
```

## Schema

The table structure of the `DoctrineDbalStore` looks like this:

| Column           | Type     | Description                                      |
|------------------|----------|--------------------------------------------------|
| id               | bigint   | The index of the whole stream (autoincrement)    |
| aggregate        | string   | The name of the aggregate                        |
| aggregate_id     | string   | The id of the aggregate                          |
| playhead         | int      | The current playhead of the aggregate            |
| event            | string   | The name of the event                            |
| payload          | json     | The payload of the event                         |
| recorded_on      | datetime | The date when the event was recorded             |
| new_stream_start | bool     | If the event is the first event of the aggregate |
| archived         | bool     | If the event is archived                         |
| custom_headers   | json     | Custom headers for the event                     |

With the help of the `SchemaDirector`, the database structure can be created, updated and deleted.

!!! tip

    You can also use doctrine [migration](migration.md) to create and keep your schema in sync.

### Schema Director

The `SchemaDirector` is responsible for creating, updating and deleting the database schema.
The `DoctrineSchemaDirector` is a concrete implementation of the `SchemaDirector` for doctrine dbal.
Additionally, it implements the `DryRunSchemaDirector` interface, to show the sql statements that would be executed.

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store
);
```

!!! note

    How to setup cli commands for schema director can be found [here](cli.md).

#### Create schema

You can create the table from scratch using the `create` method.

```php
$schemaDirector->create();
```

Or can give you back which SQL statements would be necessary for this.
Either for a dry run, or to define your own migrations.

```php
$sql = $schemaDirector->dryRunCreate();
```

#### Update schema

The update method compares the current state in the database and how the table should be structured.
As a result, the diff is executed to bring the table to the desired state.

```php
$schemaDirector->update();
```

Or can give you back which SQL statements would be necessary for this.

```php
$sql = $schemaDirector->dryRunUpdate();
```

#### Drop schema

You can also delete the table with the `drop` method.

```php
$schemaDirector->drop();
```

Or can give you back which SQL statements would be necessary for this.

```php
$sql = $schemaDirector->dryRunDrop();
```

### Doctrine Migrations

You can use [doctrine migration](https://www.doctrine-project.org/projects/migrations.html),
which is known from [doctrine orm](https://www.doctrine-project.org/projects/orm.html),
to create your schema and keep it in sync.
We have added a `DoctrineMigrationSchemaProvider` for doctrine migrations so that you just have to plug the whole thing
together.

```php
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

// event sourcing schema director configuration

$schemaDirector = new DoctrineSchemaDirector(
    $store,
    $connection
);

$schemaProvider = new DoctrineMigrationSchemaProvider($schemaDirector);

// doctrine migration configuration

$dependencyFactory = DependencyFactory::fromConnection(
    $config, 
    new ExistingConnection($connection)
);

$dependencyFactory->setService(
    SchemaProvider::class, 
    $schemaProvider
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
$stream = $store->load();
```

The load method also has a few parameters to filter, limit and sort the events.

```php
use Patchlevel\EventSourcing\Store\Criteria;

$stream = $store->load(
    new Criteria() // filter criteria
    100 // limit
    50, // offset
    true,  // latest first
);
```

#### Criteria

The `Criteria` object is used to filter the events.

```php
use Patchlevel\EventSourcing\Store\Criteria;

$criteria = new Criteria(
    aggregateName: 'profile',
    aggregateId: 'e3e3e3e3-3e3e-3e3e-3e3e-3e3e3e3e3e3e',
    fromIndex: 100,
    fromPlayhead: 2,
    archived: true,
);
```

!!! note

    The individual criteria must all apply, but not all of them have to be set.

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

    You can find more information about the `Message` object [here](event_bus.md).

!!! warning

    The stream cannot rewind, so you can only iterate over it once.
    If you want to iterate over it again, you have to call the `load` method again.

### Count

You can count the number of events in the store with the `count` method.

```php
$count = $store->count();
```

The count method also has the possibility to filter the events.

```php
use Patchlevel\EventSourcing\Store\Criteria;

$count = $store->count(
    new Criteria() // filter criteria
);
```

### Save

You can save a message with the `save` method.

```php
$store->save($message);
$store->save($message1, $message2, $message3);
$store->save(...$messages);
```

!!! note

    The saving happens in a transaction, so all messages are saved or none.    

### Delete & Update

It is not possible to delete or update events.
In event sourcing, the events are immutable.

### Transaction

There is also the possibility of executing a function in a transaction.
Then dbal takes care of starting a transaction, committing it and then possibly rollback it again.

```php
$store->transactional(function () use ($command, $bankAccountRepository) {
    $accountFrom = $bankAccountRepository->get($command->from());
    $accountTo = $bankAccountRepository->get($command->to());
    
    $accountFrom->transferMoney($command->to(), $command->amount());
    $accountTo->receiveMoney($command->from(), $command->amount());
    
    $bankAccountRepository->save($accountFrom);
    $bankAccountRepository->save($accountTo);
});
```

## Learn more

* [How to create events](events.md)
* [How to use repositories](repository.md)
* [How to dispatch events](event_bus.md)
* [How to upcast events](upcasting.md)
* [How configure cli commands](cli.md)