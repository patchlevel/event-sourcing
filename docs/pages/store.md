# Store

In the end, the events/messages have to be saved somewhere.
The library is based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html)
and offers two different store strategies.

But it is also possible to develop your own store by implementing the `Store` interface.

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

We offer two store strategies that you can choose as you like.

### Single Table Store

With the `SingleTableStore` everything is saved in one table.
The dbal connection is needed, a mapping of the aggregate class and aggregate name
and, last but not least, the table name.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\SingleTableStore;

$store = new SingleTableStore(
    $connection,
    DefaultEventSerializer::createFromPaths(['src/Event']),
    new AggregateRootRegistry([
        'profile' => Profile::class
    ]),
    'eventstore'
);
```

!!! tip

    You can switch between strategies using the [pipeline](./pipeline.md).

### Multi Table Store

With the `MultiTableStore` a separate table is created for each aggregate type.
In addition, a meta table is created by referencing all events in the correct order.
The dbal connection is needed, a mapping of the aggregate class and table name
and, last but not least, the table name for the metadata.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\MultiTableStore;

$store = new MultiTableStore(
    $connection,
    DefaultEventSerializer::createFromPaths(['src/Event']),
    new AggregateRootRegistry([
        'profile' => Profile::class
    ]),
    'eventstore'
);
```

!!! tip

    You can switch between strategies using the [pipeline](./pipeline.md).

## Transaction

Our stores also implement the `TransactionStore` interface.
This allows you to combine several aggregate interactions in one transaction
and thus ensure that everything is saved together or none of it.

Since the library is based on doctrine dbal, our implementation is just a proxy.

!!! note

    You can find more about dbal transaction [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/transactions.html).

### Begin transaction

```php
$store->transactionBegin();
```

### Commit transaction

```php
$store->transactionCommit();
```

### Rollback transaction

```php
$store->transactionRollback();
```

### Transactional function

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

## Schema Manager

With the help of the `SchemaManager`, the database structure can be created, updated and deleted.

!!! tip

    You can also use doctrine [migration](migration.md) to create and keep your schema in sync.

### Create schema

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;

(new SchemaManager())->create($store);
```

### Update schema

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;

(new SchemaManager())->update($store);
```

### Drop schema

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;

(new SchemaManager())->drop($store);
```
