# Store

In the end, the events/messages have to be saved somewhere.
The library is based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html).

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

## Store types

We only offer one Doctrine Dbal Store by default. But you can implement your own store if you want to.

### Doctrine DBAL Store

With the `DoctrineDbalStore` everything is saved in one table.
The dbal connection is needed, a mapping of the aggregate class and aggregate name
and the table name.

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

## Transaction

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

!!! tip

    To ensure that all listeners are executed for the released events 
    or that the listeners are not executed if the transaction fails, 
    you can use the [outbox](outbox.md) pattern for it.

## Schema

With the help of the `SchemaDirector`, the database structure can be created, updated and deleted.

!!! tip

    You can also use doctrine [migration](migration.md) to create and keep your schema in sync.

### Create SchemaDirector

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store
);
```

### Create schema

```php
$schemaDirector->create();
```

### Update schema

```php
$schemaDirector->update();
```

### Drop schema

```php
$schemaDirector->drop();
```
