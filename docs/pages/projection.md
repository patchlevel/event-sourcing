# Projections

With `projections` you can transform your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the event store remains untouched
and everything can always be reproduced from the events.

A projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Projector

To create a projection you need a projector with a unique ID named `projectorId`.
This projector is responsible for a specific projection.
To do this, you can use the `Projector` attribute.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('profile_1')]
final class ProfileProjector
{
    use ProjectorUtil;

    public function __construct(
        private readonly Connection $connection
    ) {
    }
}
```

!!! tip

    Add a version as suffix to the `projectorId`, 
    so you can increment it when the projection changes.
    Like `profile_1` to `profile_2`.

!!! warning

    MySQL and MariaDB don't support transactions for DDL statements.
    So you must use a different database connection for your projections.

### Subscribe

A projector can subscribe any number of events.
In order to say which method is responsible for which event, you need the `Subscribe` attribute.
There you can pass the event class to which the reaction should then take place.
The method itself must expect a `Message`, which then contains the event. 
The method name itself doesn't matter.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('profile_1')]
final class ProfileProjector
{
    use ProjectorUtil;
    
    // ...
    
    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();
    
        $this->connection->executeStatement(
            "INSERT INTO {$this->table()} (id, name) VALUES(?, ?);",
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name
            ]
        );
    }
    
    private function table(): string 
    {
        return 'projection_' . $this->projectionId();
    }
}
```

!!! warning

    You have to be careful with actions because in default it will be executed from the start of the event stream.
    Even if you change the ProjectionId, it will run again from the start.

!!! note

    You can subscribe to multiple events on the same method or you can use "*" to subscribe to all events.
    More about this can be found [here](./event_bus.md#listener).

!!! tip

    If you are using psalm then you can install the event sourcing [plugin](https://github.com/patchlevel/event-sourcing-psalm-plugin) 
    to make the event method return the correct type.

### Setup and Teardown

Projectors can have one `setup` and `teardown` method that is executed when the projection is created or deleted.
For this there are the attributes `Setup` and `Teardown`. The method name itself doesn't matter.
In some cases it may be that no schema has to be created for the projection,
as the target does it automatically, so you can skip this.

```php
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('profile_1')]
final class ProfileProjector
{
    use ProjectorUtil;
    
    // ...

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement(
            "CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL);"
        );
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    private function table(): string 
    {
        return 'projection_' . $this->projectionId();
    }
}
```

!!! warning

    If you change the `projectorID`, you must also change the table/collection name.
    Otherwise the table/collection will conflict with the old projection.

!!! note

    Most databases have a limit on the length of the table/collection name.
    The limit is usually 64 characters.

!!! tip

    You can also use the `ProjectorUtil` to build the table/collection name.

### Read Model

You can also implement your read model here. 
You can offer methods that then read the data and put it into a specific format.

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('profile_1')]
final class ProfileProjector
{
    use ProjectorUtil;

    // ...

    /**
     * @return list<array{id: string, name: string}>
     */
    public function getProfiles(): array 
    {
        return $this->connection->fetchAllAssociative("SELECT id, name FROM {$this->table()};");
    }
    
    private function table(): string 
    {
        return 'projection_' . $this->projectionId();
    }
}
```

!!! tip

    You can also use the `ProjectorUtil` to build the table/collection name.

### Versioning

As soon as the structure of a projection changes, or you need other events from the past,
the `projectorId` must be change or increment.

Otherwise, the projectionist will not recognize that the projection has changed and will not rebuild it.
To do this, you can add a version to the `projectorId`:

```php
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector('profile_2')]
final class ProfileProjector
{
   // ...
}
```

!!! warning

    If you change the `projectorID`, you must also change the table/collection name.
    Otherwise the table/collection will conflict with the old projection.

### Grouping

You can also group projectors and address these to the projectionist.
This is useful if you want to run projectors in different processes or on different servers.

```php
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector('profile_1', group: 'a')]
final class ProfileProjector
{
   // ...
}
```

!!! note

    The default group is `default` and the projectionist takes all groups if none are given to him.

### Run Mode

The run mode determines how the projector should behave when it is booted.
There are three different modes:

#### From Beginning

This is the default mode. 
The projector will start from the beginning of the event stream and process all events.

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

#[Projector('welcome_email', runMode: RunMode::FromBeginning)]
final class WelcomeEmailProjector
{
   // ...
}
```

#### From Now

Certain projectors operate exclusively on post-release events, disregarding historical data.
This is useful for projectors that are only interested in events that occur after a certain point in time.
As example, a welcome email projector that only wants to send emails to new users.

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

#[Projector('welcome_email', runMode: RunMode::FromNow)]
final class WelcomeEmailProjector
{
   // ...
}
```

#### Once

This mode is useful for projectors that only need to run once.
This is useful for projectors to create reports or to migrate data.

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

#[Projector('migration', runMode: RunMode::Once)]
final class MigrationProjector
{
   // ...
}
```

## Projectionist

The projectionist manages individual projectors and keeps the projections running.
Internally, the projectionist does this by tracking where each projector is in the event stream
and keeping all projections up to date.
He also takes care that new projectors are booted and old ones are removed again.
If something breaks, the projectionist marks the individual projections as faulty.

!!! tip

    The Projectionist was inspired by the following two blog posts:

    * [Projection Building Blocks: What you'll need to build projections](https://barryosull.com/blog/projection-building-blocks-what-you-ll-need-to-build-projections/)
    * [Managing projectors is harder than you think](https://barryosull.com/blog/managing-projectors-is-harder-than-you-think/)

## Projection ID

The projection ID is taken from the associated projector and corresponds to the projector ID.
Unlike the projector ID, the projection ID can no longer change.
If the Projector ID is changed, a new projection will be created with this new projector ID.
So there are two projections, one with the old projector ID and one with the new projector ID.

## Projection Position

Furthermore, the position in the event stream is stored for each projection.
So that the projectionist knows where the projection stopped and must continue.

## Projection Status

There is a lifecycle for each projection.
This cycle is tracked by the projectionist.

``` mermaid
stateDiagram-v2
    direction LR
    [*] --> New
    New --> Booting
    New --> Error
    Booting --> Active
    Booting --> Paused
    Booting --> Finished
    Booting --> Error
    Active --> Paused
    Active --> Finished
    Active --> Outdated
    Active --> Error
    Paused --> New
    Paused --> Booting
    Paused --> Active
    Paused --> Outdated
    Paused --> [*]
    Finished --> Active
    Finished --> Outdated
    Error --> New
    Error --> Booting
    Error --> Active
    Error --> Paused
    Error --> [*]
    Outdated --> Active
    Outdated --> [*]
```

### New

A projection is created and "new" if a projector exists with an ID that is not yet tracked.
This can happen when either a new projector has been added, the `projector id` has changed
or the projection has been manually deleted from the projection store.

### Booting

Booting status is reached when the boot process is invoked.
In this step, the "setup" method is called on the projection, if available.
And the projection is brought up to date, depending on the mode.
When the process is finished, the projection is set to active.

### Active

The active status describes the projections currently being actively managed by the projectionist.
These projections have a projector, follow the event stream and should be up-to-date.

## Paused

A projection can manually be paused. It will then no longer be updated by the projectionist.
This can be useful if you want to pause a projection for a certain period of time.
You can also reactivate the projection if you want so that it continues.

### Finished

A projection is finished if the projector has the mode `RunMode::Once`.
This means that the projection is only run once and then set to finished if it reaches the end of the event stream.
You can also reactivate the projection if you want so that it continues.

### Outdated

If an active or finished projection exists in the projection store
that does not have a projector in the source code with a corresponding projector ID,
then this projection is marked as outdated.
This happens when either the projector has been deleted
or the projector ID of a projector has changed.
In the last case there should be a new projection with the new projector ID.

An outdated projection does not automatically become active again when the projector exists again.
This happens, for example, when an old version was deployed again during a rollback.

There are two options to reactivate the projection:

* Reactivate the projection, so that the projection is active again.
* Remove the projection and rebuild it from scratch.

### Error

If an error occurs in a projector, then the target projection is set to Error.
This can happen in the create process, in the boot process or in the run process.
This projection will then no longer boot/run until the projection is reactivate or retried.

The projectionist has a retry strategy to retry projections that have failed.
It tries to reactivate the projection after a certain time and a certain number of attempts.
If this does not work, the projection is set to error and must be manually reactivated.

There are two options here:

* Reactivate the projection, so that the projection is in the previous state again.
* Remove the projection and rebuild it from scratch.

## Setup

In order for the projectionist to be able to do its work, you have to assemble it beforehand.

### Projection Store

The Projectionist uses a projection store to store the status of each projection.
We provide a Doctrine implementation of this by default.

```php
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;

$projectionStore = new DoctrineStore($connection);
```

So that the schema for the projection store can also be created,
we have to tell the `SchemaDirector` our schema configuration.
Using `ChainSchemaConfigurator` we can add multiple schema configurators.
In our case they need the `SchemaConfigurator` from the event store and projection store.

```php
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection
    new ChainDoctrineSchemaConfigurator([
        $eventStore,
        $projectionStore
    ]),
);
```

!!! note

    You can find more about schema configurator [here](./store.md) 

### Retry Strategy

The projectionist uses a retry strategy to retry projections that have failed.
Our default strategy can be configured with the following parameters:

* `baseDelay` - The base delay in seconds.
* `delayFactor` - The factor by which the delay is multiplied after each attempt.
* `maxAttempts` - The maximum number of attempts.

```php
use Patchlevel\EventSourcing\Projection\RetryStrategy\ClockBasedRetryStrategy;

$retryStrategy = new ClockBasedRetryStrategy(
    baseDelay: 5,
    delayFactor: 2,
    maxAttempts: 5,
);
```

!!! tip

    You can reactivate the projection manually or remove it and rebuild it from scratch.

### Projectionist

Now we can create the projectionist and plug together the necessary services.
The event store is needed to load the events, the Projection Store to store the projection state 
and the respective projectors. Optionally, we can also pass a retry strategy.

```php
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;

$projectionist = new DefaultProjectionist(
    $eventStore,
    $projectionStore,
    [$projector1, $projector2, $projector3],
    $retryStrategy,
);
```

## Usage

The Projectionist has a few methods needed to use it effectively.
A `ProjectionistCriteria` can be passed to all of these methods to filter the respective projectors.

```php
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;

$criteria = new ProjectionistCriteria(
    ids: ['profile_1', 'welcome_email'],
    groups: ['default']
);
```

!!! note

    An `OR` check is made for the respective criteria and all criteria are checked with an `AND`.

### Boot

So that the projectionist can manage the projections, they must be booted.
In this step, the structures are created for all new projections.
The projections then catch up with the current position of the event stream.
When the projections are finished, they switch to the active state.

```php
$projectionist->boot($criteria);
```

### Run

All active projections are continued and updated here.

```php
$projectionist->run($criteria);
```

### Teardown

If projections are outdated, they can be cleaned up here.
The projectionist also tries to remove the structures created for the projection.

```php
$projectionist->teardown($criteria);
```

### Remove

You can also directly remove a projection regardless of its status.
An attempt is made to remove the structures, but the entry will still be removed if it doesn't work.

```php
$projectionist->remove($criteria);
```

### Reactivate

If a projection had an error, you can reactivate it.
As a result, the projection gets the status active again and is then kept up-to-date again by the projectionist.

```php
$projectionist->reactivate($criteria);
```

### Pause

Pausing a projection is also possible.
The projection will then no longer be updated by the projectionist.
You can reactivate the projection if you want so that it continues.

```php
$projectionist->pause($criteria);
```

### Status

To get the current status of all projections, you can get them using the `projections` method.

```php
$projections = $projectionist->projections($criteria);

foreach ($projections as $projection) {
    echo $projection->status();
}
```

## Learn more

* [How to use CLI commands](./cli.md)
* [How to use Pipeline](./pipeline.md)
* [How to use Event Bus](./event_bus.md)
* [How to Test](./testing.md)