# CLI

This library provides a `cli` to manage the `event-sourcing` functionalities.

You can:

* Create and delete `databases`
* Create, update and delete `schemas`
* Manage `subscriptions`
* Inspect your `aggregates`, `events` and `subscribers`
* Migrate events from one `store` to another

## Database commands

There are two commands for creating and deleting a database.

* DatabaseCreateCommand: `event-sourcing:database:create`
* DatabaseDropCommand: `event-sourcing:database:drop`

## Schema commands

The database schema can also be created, updated and dropped.

* SchemaCreateCommand: `event-sourcing:schema:create`
* SchemaUpdateCommand: `event-sourcing:schema:update`
* SchemaDropCommand: `event-sourcing:schema:drop`

:::note
You can also register doctrine migration commands.
:::

## Subscription commands

To manage your subscriptions there are the following cli commands.

* SubscriptionBootCommand: `event-sourcing:subscription:boot`
* SubscriptionPauseCommand: `event-sourcing:subscription:pause`
* SubscriptionReactivateCommand: `event-sourcing:subscription:reactivate`
* SubscriptionRefreshCommand: `event-sourcing:subscription:refresh`
* SubscriptionRemoveCommand: `event-sourcing:subscription:remove`
* SubscriptionRunCommand: `event-sourcing:subscription:run`
* SubscriptionSetupCommand: `event-sourcing:subscription:setup`
* SubscriptionStatusCommand: `event-sourcing:subscription:status`
* SubscriptionTeardownCommand: `event-sourcing:subscription:teardown`

:::note
You can find out more about [subscriptions](subscription.md).
:::

## Inspector commands

The inspector is a tool to inspect the event streams.

* ShowCommand: `event-sourcing:show`
* ShowAggregateCommand: `event-sourcing:show-aggregate`
* WatchCommand: `event-sourcing:watch`

## Debug command

The debug command prints everything the library knows about your application:
all registered aggregates, all registered events and all subscribers with their subscribe methods.
It is the fastest way to check if a class was picked up by the attribute scanning.

* DebugCommand: `event-sourcing:debug` (alias `debug:event-sourcing`)

```php
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Symfony\Component\Console\Application;

/**
 * @var Application $cli
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var EventRegistry $eventRegistry
 * @var SubscriberAccessorRepository $subscriberAccessorRepository
 */
$cli->add(
    new DebugCommand(
        $aggregateRootRegistry,
        $eventRegistry,
        $subscriberAccessorRepository,
    ),
);
```
:::note
The subscriber repository is optional. If you don't pass it, the subscriber section is skipped.
:::

## Store migration command

The store migration command copies all events from one store into another one.
You need it when you switch the store implementation,
for example from the [StreamDoctrineDbalStore](store.md#streamdoctrinedbalstore)
to the [TaggableDoctrineDbalStore](store.md#taggabledoctrinedbalstore).

* StoreMigrateCommand: `event-sourcing:store:migrate`

```php
use Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand;
use Patchlevel\EventSourcing\Message\Translator\ExtractEventTagTranslator;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Application;

/**
 * @var Application $cli
 * @var Store $oldStore
 * @var Store $newStore
 */
$cli->add(
    new StoreMigrateCommand(
        $oldStore,
        $newStore,
        [new ExtractEventTagTranslator()],
    ),
);
```
The third constructor argument is a list of [translators](message.md#translator)
that are applied to every message before it is written into the new store.
The `ExtractEventTagTranslator` reads the tags from the events and adds them as `TagsHeader`,
which is what you need for a migration to the `TaggableDoctrineDbalStore`.
If both stores work with the same headers, you can leave the list empty.

Events are written in batches. You can control the batch size with the `buffer` option:

```bash
bin/console event-sourcing:store:migrate --buffer=5000
```
:::danger
The command writes into the target store, it does not clean it up first.
Make sure the target store is empty and create a backup before you run the migration.
:::

:::note
The schema of the new store has to exist before you run the command.
You can create it with the [schema commands](#schema-commands).
:::

## CLI example

A cli php file can look like this:

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Symfony\Component\Console\Application;

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$doctrineHelper = new DoctrineHelper();

/**
 * @var Connection $connection
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector($connection, $store);

/** @var SubscriptionEngine $subscriptionEngine */
$cli->addCommands([
    new Command\DatabaseCreateCommand($connection, $doctrineHelper),
    new Command\DatabaseDropCommand($connection, $doctrineHelper),
    new Command\SubscriptionBootCommand($subscriptionEngine),
    new Command\SubscriptionPauseCommand($subscriptionEngine),
    new Command\SubscriptionRunCommand($subscriptionEngine, $store),
    new Command\SubscriptionTeardownCommand($subscriptionEngine),
    new Command\SubscriptionRemoveCommand($subscriptionEngine),
    new Command\SubscriptionReactivateCommand($subscriptionEngine),
    new Command\SubscriptionRefreshCommand($subscriptionEngine),
    new Command\SubscriptionSetupCommand($subscriptionEngine),
    new Command\SubscriptionStatusCommand($subscriptionEngine),
    new Command\SchemaCreateCommand($schemaDirector),
    new Command\SchemaDropCommand($schemaDirector),
    new Command\SchemaUpdateCommand($schemaDirector),
]);

$cli->run();
```
### Doctrine Migrations

If you want to use doctrine migrations, you can register the commands like this:

```php
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Application;

/**
 * @var Connection $connection
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector($connection, $store);

/** @var ConfigurationLoader $migrationConfig */
$dependencyFactory = DependencyFactory::fromConnection(
    $migrationConfig,
    new ExistingConnection($connection),
);


$dependencyFactory->setService(
    SchemaProvider::class,
    new DoctrineMigrationSchemaProvider($schemaDirector),
);

/** @var Application $cli */
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
:::note
Here you can find more information on how to
[configure doctrine migration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/custom-configuration.html).
:::

## Learn more

* [How to configure store](store.md)
* [How to configure subscription engine](subscription.md)
