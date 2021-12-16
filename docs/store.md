# Store

In the end, the events have to be saved somewhere. 
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

> :book: You can find out more about how to create a connection 
> [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)

## Store types

We offer two store strategies that you can choose as you like.

### Single Table Store

With the `SingleTableStore` everything is saved in one table. 
The dbal connection is needed, a mapping of the aggregate class and aggregate name 
and, last but not least, the table name.

```php
use Patchlevel\EventSourcing\Store\SingleTableStore;

$store = new SingleTableStore(
    $connection,
    [
        Profile::class => 'profile'
    ],
    'eventstore'
);
```

> :book: You can switch between strategies using the [pipeline](./pipeline.md).

### Multi Table Store

With the `MultiTableStore` a separate table is created for each aggregate type. 
In addition, a meta table is created by referencing all events in the correct order. 
The dbal connection is needed, a mapping of the aggregate class and table name 
and, last but not least, the table name for the metadata.

```php
use Patchlevel\EventSourcing\Store\MultiTableStore;

$store = new MultiTableStore(
    $connection,
    [
        Profile::class => 'profile'
    ],
    'eventstore'
);
```

> :book: You can switch between strategies using the [pipeline](./pipeline.md).

## Schema Manager

With the help of the `SchemaManager`, the database structure can be created, updated and deleted.

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

## Migration

You can also manage your schema with doctrine migrations. 

In order to be able to use `doctrine/migrations`, 
you have to install the associated package.

```bash
composer require doctrine/migrations
```

We have added a `schema provider` for doctrine migrations 
so that you just have to plug the whole thing together.

```php
use Patchlevel\EventSourcing\Schema\MigrationSchemaProvider;

$schemaProvider = new MigrationSchemaProvider($store);
```

You can plug this together, for example, as follows to create CLI applications like `cli.php`:

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;

$connection = DriverManager::getConnection([
    'url' => 'mysql://user:secret@localhost/app'
]);

$config = new PhpFile('migrations.php');

$dependencyFactory = DependencyFactory::fromConnection(
    $config, 
    new ExistingConnection($connection)
);

$store = /* define your doctrine store */;

$dependencyFactory->setService(
    SchemaProvider::class, 
    new MigrationSchemaProvider($store)
);

$cli = new Application('Doctrine Migrations');
$cli->setCatchExceptions(true);

$cli->addCommands(array(
    new Command\ExecuteCommand($dependencyFactory),
    new Command\GenerateCommand($dependencyFactory),
    new Command\LatestCommand($dependencyFactory),
    new Command\ListCommand($dependencyFactory),
    new Command\MigrateCommand($dependencyFactory),
    new Command\DiffCommand($dependencyFactory),
    new Command\StatusCommand($dependencyFactory),
    new Command\VersionCommand($dependencyFactory),
));

$cli->run();
```

> :book: Here you can find more information on how to 
> [configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).

Now you can execute commands like:

```bash
cli.php migrations:diff
cli.php migrations:migrate
```