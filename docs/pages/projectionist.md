# Projectionist

The projectionist manages individual projectors and keeps the projections running.
Internally, the projectionist does this by tracking where each projector is in the event stream 
and keeping all projections up to date. 
He also takes care that new projections are booted and old ones are removed again. 
In the event of failures, the projectionist marks the individual projections as faulty.

!!! note

    You can find the basics of projections and projectors [here](./projection.md)

!!! tip

    The Projectionist was inspired by the following two blog posts:

    * [Projection Building Blocks: What you'll need to build projections](https://barryosull.com/blog/projection-building-blocks-what-you-ll-need-to-build-projections/)
    * [Managing projectors is harder than you think](https://barryosull.com/blog/managing-projectors-is-harder-than-you-think/)

## Stateful Projector

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\StatefulProjector;
use Patchlevel\EventSourcing\Projection\ProjectionId;

final class ProfileProjection implements StatefulProjector
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }
    
    public function projectionId(): ProjectionId 
    {
        return new ProjectionId(
            name: 'profile', 
            version: 1
        );
    }
    
    /**
     * @return list<array{id: string, name: string}>
     */
    public function getProfiles(): array 
    {
        return $this->connection->fetchAllAssociative(
            sprintf('SELECT id, name FROM %s;', $this->table())
        );
    }

    #[Create]
    public function create(): void
    {
        $this->connection->executeStatement(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL);', 
                $this->table()
            )
        );
    }

    #[Drop]
    public function drop(): void
    {
        $this->connection->executeStatement(
            sprintf('DROP TABLE IF EXISTS %s;', $this->table())
        );
    }

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();
    
        $this->connection->executeStatement(
            sprintf('INSERT INTO %s (`id`, `name`) VALUES(:id, :name);', $this->table()),
            [
                'id' => $profileCreated->profileId,
                'name' => $profileCreated->name
            ]
        );
    }
    
    private function table(): string 
    {
        return sprintf(
            'projection_%s_%s', 
            $this->projectionId()->name(), 
            $this->projectionId()->version()
        );
    }
}
```

## Projection Id

In order to clearly identify a projector, each projector has an identifier (Projector Id).
This Projector Id consists of a unique name and a version.

Every time something changes in the projection, like the data structure or the data itself,
the version of the projector Id must be changed.

This tells the projectionist to rebuild the projection.

!!! warning

    Use the Projector Id to define the table or target.
    This allows different versions of the same projection to exist in parallel and updating them is simplified.
    Otherwise the projectionist will not work properly.

## Projection Position

Furthermore, the position in the event stream is noted for each projection.
So that the projectionist knows where the projection stopped and must continue.

## Projection Status

There is a lifecycle for each Projector Id. 
This cycle is tracked by the projectionist and determined by the projectors.

``` mermaid
stateDiagram-v2
    New --> Booting
    Booting --> Active
    Booting --> Error
    Active --> Outdated
    Active --> Error
    Error --> Active
    Error --> New
```

### New

A projector gets the status new if this was not previously known.
This can happen when either a new projector has been added, the version has changed
or the projector has been manually deleted from the store.

### Booting

Booting status is reached when the boot process is invoked and a projector is new. 
Here the projection is built up in a separate process parallel to the currently active projections. 
As soon as the projection is built up to the current status, the status changes to active.

### Active

The active status describes the projections currently being actively managed by the projectionist.
These projections follow the event stream and are up to date.

### Outdated

As soon as a projection no longer exists in the source code, the projection is set to outdated. 
This happens when either the projector has been deleted 
or the version has been changed so that the old version is no longer needed.

### Error

If an error occurs in a projector, then the projector is set to Error. 
This projector will then no longer run until the projector is activated again. 
There are two options here:

* Reactivate the projector.
* Remove the projection and rebuild it.

## Setup

In order for the projectionist to be able to do its work, you have to assemble it beforehand.

!!! warning

    The SyncProjectorListener must be removed again so that the events are not processed directly!


### Projection Store

So that the projectionist always knows what the status and position is, it must be saved temporarily in a store.

Currently there is only the Doctrine Store.

```php
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;

$projectionStore = new DoctrineStore($connection);
```

So that the schema for the store can also be created, we have to tell the `SchemaDirector` our schema configuration.
Using ChainSchemaConfigurator we can add multiple `SchemaConfigurators`.
In our case they need the SchemaConfiguration from the event store and projector store.

```php
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection
    new ChainSchemaConfigurator([
        $eventStore,
        $projectionStore
    ]),
);
```

### Projectionist

Now we can create the projectionist:

```php
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;

$projectionist = new DefaultProjectionist(
    $eventStore,
    $projectionStore,
    $projectorRepository
);
```

## Usage

The Projectionist has a few methods needed to use it effectively. These are explained here:

### Boot

So that the projectionist can manage the projectors, they must be booted. 
In this step, all new projectors are taken and the projection is prepared like database structures.
The projections then catch up with the current status of the event stream.
When the projections are finished, they switch to the active state.

```php
$projectionist->boot();
```

### Run

In order for the projectionist to keep the projections up-to-date, the projectors would have to be running. 
All active projections are kept up to date from the event stream here.

```php
$projectionist->run();
```

### Teardown

If projections are outdated, they can be cleaned up here. 
Here the projectionist also tries to remove the structures created for the projection.

```php
$projectionist->teardown();
```

### Remove

You can also directly remove a projection regardless of its status.
Here, too, an attempt is made to remove the structures. 
But the entry will still be removed if it doesn't work.

```php
$projectionist->remove();
```

### Reactivate

If a projection had an error, you can reactivate it. 
As a result, the projection gets the status active again and is then kept up-to-date again by the projectionist.

```php
$projectionist->reactivate();
```

### State

To get the current status of all projections, you can get them using the `projections` method.

```php
$projectionist->projections();
```

!!! note

    There are also [cli commands](./cli.md) for all commands.
