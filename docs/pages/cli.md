# CLI

This library provides a `cli` to manage the `event-sourcing` functionalities.

You can:

* Create and delete `databases`
* Create, update and delete `schemas`
* Manage `projections`
* Consume `outbox` messages

## Database commands

There are two commands for creating and deleting a database.

* DatabaseCreateCommand: `event-sourcing:database:create`
* DatabaseDropCommand: `event-sourcing:database:drop`

## Schema commands

The database schema can also be created, updated and dropped.

* SchemaCreateCommand: `event-sourcing:schema:create`
* SchemaUpdateCommand: `event-sourcing:schema:update`
* SchemaDropCommand: `event-sourcing:schema:drop`

!!! note

    You can also register doctrine migration commands.

## Projection commands

To manage your projectors there are the following cli commands.

* ProjectionBootCommand: `event-sourcing:projection:boot`
* ProjectionReactiveCommand: `event-sourcing:projection:reactive`
* ProjectionRebuildCommand: `event-sourcing:projection:rebuild`
* ProjectionRemoveCommand: `event-sourcing:projection:remove`
* ProjectionRunCommand: `event-sourcing:projection:run`
* ProjectionStatusCommand: `event-sourcing:projection:status`
* ProjectionTeardownCommand: `event-sourcing:projection:teardown`

!!! note

    You can find out more about projections [here](projection.md).

## Outbox commands

Interacting with the outbox store is also possible via the cli.

* OutboxInfoCommand: `event-sourcing:outbox:info`
* OutboxConsumeCommand: `event-sourcing:outbox:consume`

!!! note

    You can find out more about outbox [here](outbox.md).

## CLI example

A cli php file can look like this:

```php
use Patchlevel\EventSourcing\Console\Command;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Symfony\Component\Console\Application;

$store = /* define your doctrine store */;
$projectionist = /* create projectionist */;

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$doctrineHelper = new DoctrineHelper();
$schemaManager = new DoctrineSchemaManager();

$cli->addCommands(array(
    new Command\DatabaseCreateCommand($store, $doctrineHelper),
    new Command\DatabaseDropCommand($store, $doctrineHelper),
    new Command\ProjectionBootCommand($projectionist),
    new Command\ProjectionRunCommand($projectionist),
    new Command\ProjectionTeardownCommand($projectionist),
    new Command\ProjectionRemoveCommand($projectionist),
    new Command\ProjectionReactivateCommand($projectionist),
    new Command\ProjectionRebuildCommand($projectionist),
    new Command\ProjectionStatusCommand($projectionist),
    new Command\SchemaCreateCommand($store, $schemaManager),
    new Command\SchemaDropCommand($store, $schemaManager),
    new Command\SchemaUpdateCommand($store, $schemaManager),
));

$cli->run();
```

### Doctrine Migrations

If you want to use doctrine migrations, you can register the commands like this:

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$connection = /* create connection */
$store = /* define your doctrine store */;

$schemaDirector = new DoctrineSchemaDirector(
    $store,
    $connection
);

$migrationConfig = /* define your migration config */;


$dependencyFactory = DependencyFactory::fromConnection(
    $migrationConfig, 
    new ExistingConnection($connection)
);


$dependencyFactory->setService(
    SchemaProvider::class, 
    new DoctrineMigrationSchemaProvider($schemaDirector)
);

$cli->addCommands([    
    new Command\ExecuteCommand($dependencyFactory, 'event-sourcing:migrations:execute'),
    new Command\GenerateCommand($dependencyFactory, 'event-sourcing:migrations:generate'),
    new Command\LatestCommand($dependencyFactory, 'event-sourcing:migrations:latest'),
    new Command\ListCommand($dependencyFactory, 'event-sourcing:migrations:list'),
    new Command\MigrateCommand($dependencyFactory, 'event-sourcing:migrations:migrate'),
    new Command\DiffCommand($dependencyFactory, 'event-sourcing:migrations:diff'),
    new Command\StatusCommand($dependencyFactory, 'event-sourcing:migrations:status'),
    new Command\VersionCommand($dependencyFactory, 'event-sourcing:migrations:version'),
]);
```

!!! note

    Here you can find more information on how to 
    [configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).
