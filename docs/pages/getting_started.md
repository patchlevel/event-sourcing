# Getting Started

In our little getting started example, we manage hotels.
We keep the example small, so we can only create hotels and let guests check in and check out.

## Define some events

First we define the events that happen in our system.

A hotel can be created with a `name` and a `id`:

```php
#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        public readonly string $hotelId,
        public readonly string $hotelName
    ) {
    }
}
```

A guest can check in by `name`:

```php
#[Event('hotel.guest_checked_in')]
final class GuestIsCheckedIn
{
    public function __construct(
        public readonly string $guestName
    ) {
    }
}
```

And also check out again:

```php
#[Event('hotel.guest_checked_out')]
final class GuestIsCheckedOut
{
    public function __construct(
        public readonly string $guestName
    ) {
    }
}
```

!!! note

    You can find out more about events [here](events.md).    

## Define aggregates

Next we need to define the aggregate. So the hotel and how the hotel should behave.
We have also defined the `create`, `checkIn` and `checkOut` methods accordingly.
These events are thrown here and the state of the hotel is also changed.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('hotel')]
final class Hotel extends AggregateRoot
{
    private string $id;
    private string $name;
    
    /**
     * @var list<string>
     */
    private array $guests;

    public function name(): string
    {
        return $this->name;
    }

    public function guests(): int
    {
        return $this->guests;
    }

    public static function create(string $id, string $hotelName): static
    {
        $self = new static();
        $self->recordThat(new HotelCreated($id, $hotelName));

        return $self;
    }

    public function checkIn(string $guestName): void
    {
        if (in_array($guestName, $this->guests, true)) {
            throw new GuestHasAlreadyCheckedIn($guestName);
        }
    
        $this->recordThat(new GuestIsCheckedIn($guestName));
    }
    
    public function checkOut(string $guestName): void
    {
        if (!in_array($guestName, $this->guests, true)) {
            throw new IsNotAGuest($guestName);
        }
    
        $this->recordThat(new GuestIsCheckedOut($guestName));
    }
    
    #[Apply]
    protected function applyHotelCreated(HotelCreated $event): void 
    {
        $this->id = $event->hotelId;
        $this->name = $event->hotelName;
        $this->guests = [];    
    }
    
    #[Apply]
    protected function applyGuestIsCheckedIn(GuestIsCheckedIn $event): void 
    {
        $this->guests[] = $event->guestName;
    }
    
    #[Apply]
    protected function applyGuestIsCheckedOut(GuestIsCheckedOut $event): void 
    {
        $this->guests = array_values(
            array_filter(
                $this->guests,
                fn ($name) => $name !== $event->guestName;
            )
        );
    }

    public function aggregateRootId(): string
    {
        return $this->id;
    }
}
```

!!! note

    You can find out more about aggregates [here](aggregate.md).

## Define projections

So that we can see all the hotels on our website and also see how many guests are currently visiting the hotels,
we need a projection for it.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\Projector;

final class HotelProjection implements Projector
{
    public function __construct(
        private readonly Connection $db
    ) {
    }
    
    /**
     * @return list<array{id: string, name: string, guests: int}>
     */
    public function getHotels(): array 
    {
        return $this->db->fetchAllAssociative('SELECT id, name, guests FROM hotel;')
    }

    #[Handle(HotelCreated::class)]
    public function handleHotelCreated(Message $message): void
    {
        $event = $message->event();
    
        $this->db->insert(
            'hotel', 
            [
                'id' => $event->hotelId, 
                'name' => $event->hotelName,
                'guests' => 0
            ]
        );
    }
    
    #[Handle(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Message $message): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests + 1 WHERE id = ?;',
            [$message->aggregateId()]
        );
    }
    
    #[Handle(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Message $message): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests - 1 WHERE id = ?;',
            [$message->aggregateId()]
        );
    }
    
    #[Create]
    public function create(): void
    {
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS hotel (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);');
    }

    #[Drop]
    public function drop(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS hotel;');
    }
}
```

!!! note

    You can find out more about projections [here](projection.md).

## Processor

In our example we also want to send an email to the head office as soon as a guest is checked in.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;

final class SendCheckInEmailProcessor extends Subscriber
{
    public function __construct(
        private readonly Mailer $mailer
    ) {
    }

    #[Handle(GuestIsCheckedIn::class)]
    public function onGuestIsCheckedIn(Message $message): void
    {
        $this->mailer->send(
            'hq@patchlevel.de',
            'Guest is checked in',
            sprintf('A new guest named "%s" is checked in', $message->event()->guestName)
        );
    }
}
```

!!! note

    You can find out more about processor [here](processor.md).

## Configuration

After we have defined everything, we still have to plug the whole thing together:

=== "Container"

    ```php
    use Patchlevel\EventSourcing\Container\ConfigBuilder;
    use Patchlevel\EventSourcing\Container\DefaultContainer;
    use Psr\Container\ContainerInterface;
    
    $config = (new ConfigBuilder())
        ->singleTable()
        ->databaseUrl('mysql://user:secret@localhost/app')
        ->addAggregatePath('src/Domain/Hotel')
        ->addEventPath('src/Domain/Hotel/Event')
        ->addProjector(HotelProjection::class)
        ->addProcessor(SendCheckInEmailProcessor::class)
        ->build();
        
    $container = new DefaultContainer(
        $config,
        [
            HotelProjection::class => fn(DefaultContainer $container) 
                => new HotelProjection($container->connection()),
            SendCheckInEmailProcessor::class => fn(DefaultContainer $container) 
                => new SendCheckInEmailProcessor($container->get('mailer')),
        ]
    );
    
    $hotelRepository = $container->repository(Hotel::class);
    ```

=== "Manuel"

    ```php
    use Doctrine\DBAL\DriverManager;
    use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
    use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
    use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
    use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
    use Patchlevel\EventSourcing\Store\SingleTableStore;
    
    $connection = DriverManager::getConnection([
        'url' => 'mysql://user:secret@localhost/app'
    ]);
    
    $mailer = /* your own mailer */;
    
    $hotelProjection = new HotelProjection($connection);
    
    $projectorRepository = new ProjectorRepository([
        $hotelProjection,
    ]);
    
    $eventBus = new DefaultEventBus();
    $eventBus->addListener(new SyncProjectorListener($projectorRepository));
    $eventBus->addListener(new SendCheckInEmailProcessor($mailer));
    
    $serializer = DefaultEventSerializer::createFromPaths(['src/Domain/Hotel/Event']);
    $aggregateRegistry = (new AttributeAggregateRootRegistryFactory)->create(['src/Domain/Hotel']);
    
    $store = new SingleTableStore(
        $connection,
        $serializer,
        $aggregateRegistry
    );
    
    $repositoryManager = new DefaultRepositoryManager(
        $aggregateRegistry,
        $store,
        $eventBus
    );
    
    $hotelRepository = $repositoryManager->get(Hotel::class);
    ```

!!! note

    You can find out more about stores [here](store.md).

## Database setup

So that we can actually write the data to a database,
we need the associated schema and databases.

=== "Container"

    ```php
    use Patchlevel\EventSourcing\Projector\ProjectorHelper;
    
    $container->schemaDirector()->create();
    (new ProjectorHelper())->createProjection(
        ...$container->projectorRepository()->projectors()
    );
    ```

=== "Manuel"

    ```php
    use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
    use Patchlevel\EventSourcing\Projector\ProjectorHelper;
    
    $schemaDirector = new DoctrineSchemaDirector(
        $store,
        $connection
    );
    
    $schemaDirector->create();
    (new ProjectorHelper())->createProjection(...$projectorRepository->projectors());
    ```

!!! note

    you can use the predefined [cli commands](cli.md) for this.

## Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
$hotel1 = Hotel::create('1', 'HOTEL');
$hotel1->checkIn('David');
$hotel1->checkIn('Daniel');
$hotel1->checkOut('David');

$hotelRepository->save($hotel1);

$hotel2 = $hotelRepository->load('2');
$hotel2->checkIn('David');
$hotelRepository->save($hotel2);

$hotels = $hotelProjection->getHotels();
```

!!! note

    An aggregateId can be an **uuid**, you can find more about this [here](uuid.md).

## Result

!!! success

    We have successfully implemented and used event sourcing.

    Feel free to browse further in the documentation for more detailed information. 
    If there are still open questions, create a ticket on Github and we will try to help you.