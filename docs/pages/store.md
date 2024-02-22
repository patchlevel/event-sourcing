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

### Create

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store
);
```

### Usage

#### Create schema

```php
$schemaDirector->create();
```

#### Update schema

```php
$schemaDirector->update();
```

#### Drop schema

```php
$schemaDirector->drop();
```

### Migration

You can use doctrine migration, which is known from doctrine orm, to create your schema and keep it in sync.
We have added a `schema provider` for doctrine migrations so that you just have to plug the whole thing together.

```php
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $store,
    $connection
);

$schemaProvider = new DoctrineMigrationSchemaProvider($schemaDirector);
```


## CLI example

You can plug this together, for example, as follows to create CLI applications like `cli.php`:

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$connection = DriverManager::getConnection([
    'url' => 'mysql://user:secret@localhost/app'
]);

$config = new PhpFile('migrations.php');

$dependencyFactory = DependencyFactory::fromConnection(
    $config, 
    new ExistingConnection($connection)
);

$schemaDirector = new DoctrineSchemaDirector(
    $store,
    $connection
);

$dependencyFactory->setService(
    SchemaProvider::class, 
    new DoctrineMigrationSchemaProvider($schemaDirector)
);

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$cli->addCommands([

    // other cli commands
    
    new Command\ExecuteCommand($dependencyFactory, 'event-sourcing:migrations:execute'),
    new Command\GenerateCommand($dependencyFactory, 'event-sourcing:migrations:generate'),
    new Command\LatestCommand($dependencyFactory, 'event-sourcing:migrations:latest'),
    new Command\ListCommand($dependencyFactory, 'event-sourcing:migrations:list'),
    new Command\MigrateCommand($dependencyFactory, 'event-sourcing:migrations:migrate'),
    new Command\DiffCommand($dependencyFactory, 'event-sourcing:migrations:diff'),
    new Command\StatusCommand($dependencyFactory, 'event-sourcing:migrations:status'),
    new Command\VersionCommand($dependencyFactory, 'event-sourcing:migrations:version'),
]);

$cli->run();
```

!!! note

    Here you can find more information on how to 
    [configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).


## Migration commands

There are some commands to use the migration feature.

* ExecuteCommand: `event-sourcing:migrations:execute`
* GenerateCommand: `event-sourcing:migrations:generate`
* LatestCommand: `event-sourcing:migrations:latest`
* ListCommand: `event-sourcing:migrations:list`
* MigrateCommand: `event-sourcing:migrations:migrate`
* DiffCommand: `event-sourcing:migrations:diff`
* StatusCommand: `event-sourcing:migrations:status`
* VersionCommand: `event-sourcing:migrations:version`
