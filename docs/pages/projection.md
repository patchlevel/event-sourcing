# Projections

With `projections` you can create your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the source of truth remains untouched
and everything can always be reproduced from the events.

The target of a projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Define Projector

To create a projection you need a projector.
In this example we always create a new data set in a relational database when a profile is created:

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorId;

final class ProfileProjection implements Projector
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }
    
    public function projectorId(): ProjectorId 
    {
        return new ProjectorId(
            name: 'profile', 
            version: 1
        );
    }
    
    /**
     * @return list<array{id: string, name: string}>
     */
    public function getProfiles(): array 
    {
        return $this->connection->fetchAllAssociative('SELECT id, name FROM projection_profile;');
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

Each projector needs an `projectorId` composed of a unique name and a version number.
With the help of this information, a projection can be clearly identified. More on that later.

!!! note

    In the synchronous variant, the projector Id is not used. 
    This only really comes into play with the [Projectionist](./projectionist.md).

Projectors can also have one `create` and `drop` method that is executed when the projection is created or deleted.
In some cases it may be that no schema has to be created for the projection, as the target does it automatically.
To do this, you must add either the `Create` or `Drop` attribute to the method. The method name itself doesn't matter.

Otherwise, a projector can have any number of handle methods that are called for certain defined events.
In order to say which method is responsible for which event, you need the `Handle` attribute.
As the first parameter, you must pass the event class to which the reaction should then take place.
The method itself must expect a `Message`, which then contains the event. The method name itself doesn't matter.

As soon as the event has been dispatched, the appropriate methods are then executed.
Several projectors can also listen to the same event.

!!! danger

    You should not execute any actions with projectors, 
    otherwise these will be executed again if you rebuild the projection!

!!! tip

    If you are using psalm then you can install the event sourcing [plugin](https://github.com/patchlevel/event-sourcing-psalm-plugin) 
    to make the event method return the correct type.

## Projector Repository

The projector repository can hold and make available all projectors.

```php
use Patchlevel\EventSourcing\Projection\DefaultProjectorRepository;

$projectorRepository = new DefaultProjectorRepository([
    new ProfileProjection($connection)
]);
```

## Setup Projection

A projection schama or database usually has to be created beforehand.
And with a rebuild, the projection has to be deleted.
The Projector Helper can help with this:

### Create Projection Schema

With this you can prepare the projection:

```php
use Patchlevel\EventSourcing\Projection\ProjectorHelper;

(new ProjectorHelper())->createProjection(new ProfileProjection($connection));
(new ProjectorHelper())->createProjection(...$projectionRepository->projectors());
```

### Drop Projection Schema

The projection can also be removed again:

```php
use Patchlevel\EventSourcing\Projection\ProjectorHelper;

(new ProjectorHelper())->dropProjection(new ProfileProjection($connection));
(new ProjectorHelper())->dropProjection(...$projectionRepository->projectors());
```

## Handle Message

The helper also offers methods to process messages:

```php
use Patchlevel\EventSourcing\Projection\ProjectorHelper;

(new ProjectorHelper())->handleMessage($message, new ProfileProjection($connection));
(new ProjectorHelper())->handleMessage($message, ...$projectionRepository->projectors());
```

## Sync Projector Listener

The simplest configuration is to run the projectors synchronously.
Says that you listen to the event bus and update the projections directly.
Here you can use the `SyncProjectorListener`.

```php
use Patchlevel\EventSourcing\Projection\SyncProjectorListener

$eventBus->addListener(
    new SyncProjectorListener($projectorRepository)
);
```

!!! note

    You can find out more about the event bus [here](./event_bus.md).

!!! note

    In order to exploit the full potential, the [projectionist](./projectionist.md) should be used in production.