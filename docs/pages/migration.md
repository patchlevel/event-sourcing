# Migration

You can use doctrine migration, which is known from doctrine orm, to create your schema and keep it in sync.

!!! warning

    To use the migration CLI commands, you have to configure the [CLI](cli.md) beforehand.

## Installation

In order to be able to use `doctrine/migrations`,
you have to install the associated package.

```bash
composer require doctrine/migrations
```

## Configure Migration Schema Provider

We have added a `schema provider` for doctrine migrations
so that you just have to plug the whole thing together.

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

- ExecuteCommand: `event-sourcing:migrations:execute`
- GenerateCommand: `event-sourcing:migrations:generate`
- LatestCommand: `event-sourcing:migrations:latest`
- ListCommand: `event-sourcing:migrations:list`
- MigrateCommand: `event-sourcing:migrations:migrate`
- DiffCommand: `event-sourcing:migrations:diff`
- StatusCommand: `event-sourcing:migrations:status`
- VersionCommand: `event-sourcing:migrations:version`
