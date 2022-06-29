# Projections

With `projections` you can create your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the source of truth remains untouched
and everything can always be reproduced from the events.

The target of a projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Define Projection

In this example we always create a new data set in a relational database when a profile is created:

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Create]
    public function create(): void
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS projection_profile (id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL);');
    }

    #[Drop]
    public function drop(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS projection_profile;');
    }

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();
    
        $this->connection->executeStatement(
            'INSERT INTO projection_profile (`id`, `name`) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId,
                'name' => $profileCreated->name
            ]
        );
    }
}
```

!!! danger

    You should not execute any actions with projections, 
    otherwise these will be executed again if you rebuild the projection!

!!! tip

    If you are using psalm then you can install the event sourcing [plugin](https://github.com/patchlevel/event-sourcing-psalm-plugin) 
    to make the event method return the correct type.

Projections have a `create` and a `drop` method that is executed when the projection is created or deleted.
In some cases it may be that no schema has to be created for the projection, as the target does it automatically.

In order for the projection to know which method is responsible for which event,
the methods must be given the `Handle` attribute with the respective event class name.

As soon as the event has been dispatched, the appropriate methods are then executed.
Several projections can also listen to the same event.

## Register projections

So that the projections are known and also executed, you have to add them to the `ProjectionHandler`.
Then add this to the event bus using the `ProjectionListener`.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionListener;

$profileProjection = new ProfileProjection($connection);
$messageProjection = new MessageProjection($connection);

$projectionHandler = new MetadataAwareProjectionHandler([
    $profileProjection,
    $messageProjection,
]);

$eventBus->addListener(new ProjectionListener($projectionHandler));
```

!!! note

    You can find out more about the event bus [here](./event_bus.md).

## Setup Projection

A projection schama or database usually has to be created beforehand.
And with a rebuild, the projection has to be deleted.
To make this possible, projections have two methods `create` and `drop` that can be defined and executed.

### Create Projection Schema

Or for all projections in the `MetadataAwareProjectionHandler`:

```php
$projectionRepository = new MetadataAwareProjectionHandler([
    $profileProjection,
    $messageProjection,
]);

$projectionRepository->create();
```

### Drop Projection Schema

Or for all projections in the `MetadataAwareProjectionHandler`:

```php
$projectionRepository = new MetadataAwareProjectionHandler([
    $profileProjection,
    $messageProjection,
]);

$projectionRepository->drop();
```
