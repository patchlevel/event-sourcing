# CLI

The library also offers `symfony` cli commands to create or delete `databases`. 
It is also possible to manage the `schema` and `projections`. 
These commands are `optional` and only wrap existing functionalities 
that are also available in this way.

## Database commands

There are two commands for creating and deleting a database.

* DatabaseCreateCommand: `event-sourcing:database:create`
* DatabaseDropCommand: `event-sourcing:database:drop`

## Schema commands

The database schema can also be created, updated and dropped.

* SchemaCreateCommand: `event-sourcing:schema:create`
* SchemaUpdateCommand: `event-sourcing:schema:update`
* SchemaDropCommand: `event-sourcing:schema:drop`

> :book: You can also register doctrine migration commands,
> see the [store](./store.md) documentation for this.

## Projection commands

The creation, deletion and rebuilding of the projections is also possible via the cli.

* ProjectionCreateCommand: `event-sourcing:projection:create`
* ProjectionDropCommand: `event-sourcing:projection:drop`
* ProjectionRebuildCommand: `event-sourcing:projection:rebuild`

> :book: The [pipeline](./pipeline.md) will be used to rebuild the projection.

## CLI example

A cli php file can look like this:

```php
use Patchlevel\EventSourcing\Console\Command;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Symfony\Component\Console\Application;

$store = /* define your doctrine store */;
$projectionRepository = /* create a project repository */;

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$doctrineHelper = new DoctrineHelper();
$schemaManager = new DoctrineSchemaManager();

$cli->addCommands(array(
    new Command\DatabaseCreateCommand($store, $doctrineHelper),
    new Command\DatabaseDropCommand($store, $doctrineHelper),
    new Command\ProjectionCreateCommand($projectionRepository),
    new Command\ProjectionDropCommand($projectionRepository),
    new Command\ProjectionRebuildCommand($store, $projectionRepository),
    new Command\SchemaCreateCommand($store, $schemaManager),
    new Command\SchemaDropCommand($store, $schemaManager),
    new Command\SchemaUpdateCommand($store, $schemaManager),
));

$cli->run();
```

> :book: You can also register doctrine migration commands, 
> see the [store](./store.md) documentation for this.
