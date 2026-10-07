# CLI

This library provides a `cli` to manage the `event-sourcing` functionalities.
The commands are [symfony console](https://symfony.com/doc/current/components/console.html) commands,
so you can register them in any symfony console application.

You can:

* Create and delete `databases`
* Create, update and delete `schemas`
* Manage `subscriptions`
* Monitor and manage `subscriptions` in an interactive dashboard
* Inspect your `aggregates`, `events` and `subscribers`
* Migrate events from one `store` to another

:::note
The examples on this page use `bin/console` as the entry point.
How to build such a cli file is shown in the [CLI example](#cli-example).
:::

## Database commands

The database commands create and delete the database configured in your doctrine connection.
They are mostly useful for local development and test environments.

### Create database

The `event-sourcing:database:create` command creates the database.

```bash
bin/console event-sourcing:database:create
```
With the `--if-not-exists` option, the command does not fail if the database already exists.

```bash
bin/console event-sourcing:database:create --if-not-exists
```
### Drop database

The `event-sourcing:database:drop` command deletes the database.
Without the `--force` option, the command only prints which database would be dropped.

```bash
bin/console event-sourcing:database:drop --force
```
With the `--if-exists` option, the command does not fail if the database does not exist.

:::danger
Dropping the database deletes all your events. They cannot be restored.
:::

## Schema commands

The schema commands manage the tables the library needs,
for example the event store table and the subscription store table.
They use the [schema director](store.md#schema), which knows all tables of the configured stores.

All schema commands support the `--dry-run` option.
Instead of executing anything, the command prints the SQL queries it would run,
so you can review the changes before applying them.

```bash
bin/console event-sourcing:schema:update --dry-run
```
### Create schema

The `event-sourcing:schema:create` command creates all tables.
Use it once when you set up a new database.

```bash
bin/console event-sourcing:schema:create
```
### Update schema

The `event-sourcing:schema:update` command compares the existing tables with the expected schema
and applies the differences, for example after a library update.
Without the `--force` option, the command does nothing.

```bash
bin/console event-sourcing:schema:update --force
```
### Drop schema

The `event-sourcing:schema:drop` command deletes all tables of the library.
Without the `--force` option, the command does nothing.

```bash
bin/console event-sourcing:schema:drop --force
```
:::danger
Dropping the schema deletes all your events. They cannot be restored.
:::

:::tip
In production, we recommend [doctrine migrations](#doctrine-migrations) instead of the schema commands,
so that every schema change is versioned and reviewed.
:::

## Subscription commands

The subscription commands control the [subscription engine](subscription.md).
They move [subscriptions](subscription.md) through their lifecycle and show their current status.

### Filter subscriptions

Most subscription commands can be limited to certain subscriptions.
Use `--id` to select subscriptions by their subscriber ID and `--group` to select them by their group.
Both options can be passed multiple times.

```bash
bin/console event-sourcing:subscription:boot --id=profile_1 --id=welcome_email
bin/console event-sourcing:subscription:run --group=projector
```
:::note
Ids are combined with `OR`, groups are combined with `OR`, and both options together are combined with `AND`.
Without any filter, the command applies to all subscriptions.
:::

### Setup

The `event-sourcing:subscription:setup` command sets up all new subscriptions.
The subscription engine calls the `setup` method of the subscriber, if available,
for example to create the table of a projection.
Afterwards the subscription is booting or, depending on the run mode, already active.

```bash
bin/console event-sourcing:subscription:setup
```
With `--skip-booting`, the subscriptions skip the booting status and become active right away.
They then catch up with the event store in the [run command](#run) instead of the boot command.

### Boot

The `event-sourcing:subscription:boot` command lets all booting subscriptions catch up with the event store.
After that, they are active, or finished for subscribers with `RunMode::Once`.
The command stops as soon as all subscriptions have caught up.

```bash
bin/console event-sourcing:subscription:boot
```
With `--setup`, the command first sets up new subscriptions, so you can do both steps in one call.

```bash
bin/console event-sourcing:subscription:boot --setup
```
The command processes the events in chunks. The `--message-limit` option (default `1000`)
defines how many events are processed per run.
If the boot is aborted by a [worker limit](#worker-options) before all subscriptions caught up,
the command exits with code `1`.

### Run

The `event-sourcing:subscription:run` command is the long-running worker.
It keeps the active subscriptions up to date by processing new events as they are recorded.

```bash
bin/console event-sourcing:subscription:run
```
By default, the command looks for new events every second (`--sleep=1000`)
and processes up to 100 events per run (`--message-limit=100`).
If your store supports it (`StreamDoctrineDbalStore` and `TaggableDoctrineDbalStore` with PostgreSQL),
the worker does not poll but waits for a notification from the database, so new events are processed right away.

:::warning
The run command only handles active subscriptions.
Subscriptions that are new or booting have to be set up and booted first.
After adding a new subscriber, run the setup and boot step and restart the run command.
:::

### Worker options

The boot and run commands are workers and support the following options:

| Option                  | Description                                                                        |
|-------------------------|------------------------------------------------------------------------------------|
| `--run-limit`           | Stop the worker after this number of runs.                                         |
| `--message-limit`       | How many events are processed per run.                                             |
| `--memory-limit`        | Stop the worker if it uses more memory than this, e.g. `256M` or `250MB`.          |
| `--time-limit`          | Stop the worker after this number of seconds.                                      |
| `--sleep`               | How many milliseconds the worker waits between two runs.                           |
| `--restart-signal-file` | Stop the worker when this file is touched after it has started.                    |
| `--heartbeat-file`      | Touch this file on start and after every run, and remove it when the worker stops. |

```bash
bin/console event-sourcing:subscription:run --memory-limit=250MB --time-limit=3600
```
:::tip
Use the memory and time limits together with a process manager like supervisor or systemd,
which restarts the worker after it stopped. This prevents memory leaks from piling up
and makes sure that a deployment is picked up.
:::

The worker stops gracefully on `SIGTERM` and `SIGINT`, so it finishes the current run before it exits.
If a run hits the `--message-limit`, the next run starts immediately without waiting for the sleep timer,
so a lagging subscription catches up as fast as possible.

#### Restart after a deployment

Long-running workers keep the code they were started with.
Pass a `--restart-signal-file` and touch the file during the deployment.
All workers that were started before stop after their current run and are restarted by your process manager.

```bash
bin/console event-sourcing:subscription:run --restart-signal-file=var/worker-restart

# during deployment
touch var/worker-restart
```
:::note
All workers that should restart have to see the same file, e.g. on a shared volume.
:::

#### Heartbeat

With `--heartbeat-file` you can detect a worker that is stuck,
e.g. with a liveness probe that checks how old the file is.

```bash
bin/console event-sourcing:subscription:run --heartbeat-file=/tmp/worker-heartbeat
```
:::warning
The heartbeat file is only updated between runs.
Choose the threshold of your probe larger than your longest run plus the sleep timer.
:::

### Status

The `event-sourcing:subscription:status` command shows a table of all subscriptions
with their group, run mode, position, status and error message.
Use it to check whether your subscriptions are up to date or have an error.

```bash
bin/console event-sourcing:subscription:status
bin/console event-sourcing:subscription:status --group=projector
```
Pass a subscription ID to show the details of one subscription.
If the subscription has an error, the command also prints the error message and the stack trace.

```bash
bin/console event-sourcing:subscription:status profile_1
```
### Pause

The `event-sourcing:subscription:pause` command pauses subscriptions.
The subscription engine no longer processes events for them until they are reactivated.
This is useful, for example, if a third party system used by a processor is down.

```bash
bin/console event-sourcing:subscription:pause --id=welcome_email
```
### Reactivate

The `event-sourcing:subscription:reactivate` command reactivates paused, finished, detached,
error or failed subscriptions. The subscription then continues from its current position.
A subscription with an error returns to the status it had before the error,
all others become active.
Use it after you fixed the cause of an error.

```bash
bin/console event-sourcing:subscription:reactivate --id=welcome_email
```
:::note
Only subscriptions whose subscriber exists in the code can be reactivated.
:::

### Refresh

The `event-sourcing:subscription:refresh` command updates the stored subscriptions
if you changed the metadata of a subscriber in the code, for example the run mode or the group.
The position and status of the subscriptions stay untouched.

```bash
bin/console event-sourcing:subscription:refresh
```
:::note
This command needs a subscription engine that supports refreshing, like the `DefaultSubscriptionEngine`.
:::

### Teardown

The `event-sourcing:subscription:teardown` command cleans up detached subscriptions.
A subscription is detached if its subscriber no longer exists in the code,
for example because you deleted it or created a new version with a new subscriber ID.
The subscription engine calls the `teardown` method or runs the cleanup tasks of the subscriber, if available,
and removes the subscription afterwards.

```bash
bin/console event-sourcing:subscription:teardown
```
:::note
Without [cleanup tasks](subscription.md#cleanup), a teardown is only possible if the code of the subscriber still exists.
Otherwise, the subscription stays detached and you can remove it with the [remove command](#remove).
:::

### Remove

The `event-sourcing:subscription:remove` command removes subscriptions regardless of their status.
The subscription engine tries to call the `teardown` method, if available,
but removes the subscription even if this fails.
If the subscriber still exists, the subscription is new again and can be set up and booted from scratch.
This is the way to rebuild a projection.

```bash
bin/console event-sourcing:subscription:remove --id=profile_1
```
Without any filter, the command asks for confirmation before it removes all subscriptions.
Use `--force` to skip the question.

:::danger
Removing a subscription deletes the data of its projection, if the subscriber has a `teardown` method.
:::

### Typical workflow

On every deployment, stop the old run command, set up and boot new subscriptions and start the run command again.
Once the old version of your application no longer serves requests, clean up the outdated subscriptions.

```bash
bin/console event-sourcing:subscription:boot --setup
# start bin/console event-sourcing:subscription:run
# wait until the old application is gone
bin/console event-sourcing:subscription:teardown
```
If a subscription has an error, look at the details and reactivate it after you fixed the problem.

```bash
bin/console event-sourcing:subscription:status welcome_email
bin/console event-sourcing:subscription:reactivate --id=welcome_email
```
:::note
You can find out more about the [subscription lifecycle](subscription.md#subscription-status).
:::

## Subscription dashboard

The subscription dashboard is an interactive, full screen terminal UI inspired by k9s.
It shows all subscriptions in a live updating table with their status, position and lag,
and lets you run the subscription commands directly on the selected subscriptions.

* SubscriptionDashboardCommand: `event-sourcing:subscription:dashboard`

:::experimental
The dashboard is experimental and may change in a minor release.
:::

The dashboard is built on the [Symfony TUI component](https://symfony.com/doc/current/tui.html),
which is an optional dependency and requires PHP 8.4 or newer.

```bash
composer require symfony/tui
```
Register the command with the subscription engine and the store.
The store is optional, it is used to show how far each subscription is behind.

```php
use Patchlevel\EventSourcing\Console\Command\SubscriptionDashboardCommand;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Symfony\Component\Console\Application;

/**
 * @var Application $cli
 * @var SubscriptionEngine $subscriptionEngine
 * @var Store $store
 */
$cli->add(
    new SubscriptionDashboardCommand(
        $subscriptionEngine,
        $store,
    ),
);
```
Like the other subscription commands, you can limit the dashboard to some subscriptions with `--id` and `--group`.
With `--refresh` you set how often the data is reloaded in seconds,
and `--message-limit` sets how many messages a run, boot or rebuild processes at once.

```bash
bin/console event-sourcing:subscription:dashboard --group=projector --refresh=1
```
### Keyboard shortcuts

Actions apply to the marked subscriptions, or to the selected one if nothing is marked.
The dashboard only offers actions that fit the status of the subscription,
for example `pause` only for active, booting or failing subscriptions.

| Key | Action |
|---|---|
| `s` | Setup new subscriptions |
| `b` | Boot subscriptions in booting state |
| `r` | Run active subscriptions |
| `p` | Pause subscriptions |
| `a` | Reactivate subscriptions |
| `f` | Refresh group, run mode and cleanup tasks |
| `t` | Teardown detached subscriptions |
| `ctrl-d` | Remove subscriptions |
| `shift-r` | Rebuild (remove and boot) subscriptions |
| `enter` | Show the details and the error of a subscription |
| `/` | Filter by id, group, status or run mode |
| `space` | Mark or unmark a subscription |
| `esc` | Go back, clear the marks or clear the filter |
| `?` | Show all shortcuts |
| `q` | Quit |

:::warning
Run, boot and rebuild are executed in the dashboard process and block the UI until they are done.
Use the `--message-limit` option to keep them short and the [run command](#subscription-commands) as worker.
:::

:::danger
Remove, teardown and rebuild delete the data of the subscribers.
The dashboard asks for a confirmation before it executes them.
:::

## Inspector commands

The inspector commands display the events in your store.
They help you to debug your application and to understand what happened in an aggregate.

### Show events

The `event-sourcing:show` command shows the latest events of the store, newest first.
After each page, it asks whether to show the next events.

```bash
bin/console event-sourcing:show
```
| Option      | Description                                                                 |
|-------------|-----------------------------------------------------------------------------|
| `--limit`   | How many events are displayed per page (default `10`, `0` shows all).       |
| `--forward` | Start with the oldest event instead of the newest.                          |
| `--stream`  | Only show events of the given stream, wildcards like `profile-*` are allowed. |

```bash
bin/console event-sourcing:show --stream="profile-*" --limit=20
```
:::note
The `--stream` option needs the [StreamDoctrineDbalStore](store.md#streamdoctrinedbalstore).
:::

### Show aggregate

The `event-sourcing:show-aggregate` command shows all events of one aggregate.
Pass the aggregate name and the aggregate ID as arguments.
If you leave them out, the command asks for them.

```bash
bin/console event-sourcing:show-aggregate profile 018d6a1c-5f2b-7f3e-9c4a-2b6a1f0e8d7c
```
### Watch events

The `event-sourcing:watch` command prints new events live as they are saved.
It is a worker like the subscription run command and supports the same
[worker options](#worker-options), except `--message-limit`.

```bash
bin/console event-sourcing:watch
```
You can limit the output to one stream with the `--stream` option.

```bash
bin/console event-sourcing:watch --stream="profile-*"
```
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

:::tip
If you use the [container](container.md), `Factory::commands()` returns all commands already configured.
:::

A cli php file can look like this:

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\Command;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Symfony\Component\Console\Application;

$cli = new Application('Event-Sourcing CLI');
$cli->setCatchExceptions(true);

$doctrineHelper = new DoctrineHelper();

/**
 * @var Connection $connection
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector($connection, $store);

/**
 * @var SubscriptionEngine $subscriptionEngine
 * @var EventSerializer $eventSerializer
 * @var HeadersSerializer $headersSerializer
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var EventRegistry $eventRegistry
 * @var SubscriberAccessorRepository $subscriberAccessorRepository
 */
$cli->addCommands([
    new Command\DatabaseCreateCommand($connection, $doctrineHelper),
    new Command\DatabaseDropCommand($connection, $doctrineHelper),
    new Command\SchemaCreateCommand($schemaDirector),
    new Command\SchemaDropCommand($schemaDirector),
    new Command\SchemaUpdateCommand($schemaDirector),
    new Command\SubscriptionSetupCommand($subscriptionEngine),
    new Command\SubscriptionBootCommand($subscriptionEngine),
    new Command\SubscriptionRunCommand($subscriptionEngine, $store),
    new Command\SubscriptionStatusCommand($subscriptionEngine),
    new Command\SubscriptionPauseCommand($subscriptionEngine),
    new Command\SubscriptionReactivateCommand($subscriptionEngine),
    new Command\SubscriptionRefreshCommand($subscriptionEngine),
    new Command\SubscriptionTeardownCommand($subscriptionEngine),
    new Command\SubscriptionRemoveCommand($subscriptionEngine),
    new Command\ShowCommand($store, $eventSerializer, $headersSerializer),
    new Command\ShowAggregateCommand($store, $eventSerializer, $headersSerializer, $aggregateRootRegistry),
    new Command\WatchCommand($store, $eventSerializer, $headersSerializer),
    new Command\DebugCommand($aggregateRootRegistry, $eventRegistry, $subscriberAccessorRepository),
]);

$cli->run();
```
:::note
The `SubscriptionBootCommand` and `SubscriptionRunCommand` accept a PSR-14 event dispatcher as an optional last argument.
It receives the worker events, for example to reset services between two runs.
:::

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
* [How to configure the container](container.md)
* [How to configure subscription engine](subscription.md)
* [How to use subscriptions](subscription.md#usage)
