# Getting Started

In our little getting started example, we manage hotels.
We keep the example small, so we can only create hotels and let guests check in and check out.

## Define some events

First we define the events that happen in our system.

A hotel can be created with a `name` and a `id`:

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[IdNormalizer(Uuid::class)]
        public readonly Uuid $hotelId,
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
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('hotel')]
final class Hotel extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
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

    public static function create(Uuid $id, string $hotelName): static
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
}
```

!!! note

    You can find out more about aggregates [here](aggregate.md).

## Define projections

So that we can see all the hotels on our website and also see how many guests are currently visiting the hotels,
we need a projection for it. To create a projection we need a projector. 
Each projector is then responsible for a specific projection and version.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('hotel')]
final class HotelProjector
{
    use ProjectorUtil;

    public function __construct(
        private readonly Connection $db
    ) {
    }
    
    /**
     * @return list<array{id: string, name: string, guests: int}>
     */
    public function getHotels(): array 
    {
        return $this->db->fetchAllAssociative("SELECT id, name, guests FROM ${this->table()};");
    }

    #[Subscribe(HotelCreated::class)]
    public function handleHotelCreated(Message $message): void
    {
        $event = $message->event();
    
        $this->db->insert(
            $this->table(), 
            [
                'id' => $message->aggregateId(), 
                'name' => $event->hotelName,
                'guests' => 0
            ]
        );
    }
    
    #[Subscribe(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Message $message): void
    {
        $this->db->executeStatement(
            "UPDATE ${this->table()} SET guests = guests + 1 WHERE id = ?;",
            [$message->aggregateId()]
        );
    }
    
    #[Subscribe(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Message $message): void
    {
        $this->db->executeStatement(
            "UPDATE ${this->table()} SET guests = guests - 1 WHERE id = ?;",
            [$message->aggregateId()]
        );
    }
    
    #[Setup]
    public function create(): void
    {
        $this->db->executeStatement("CREATE TABLE IF NOT EXISTS ${this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);");
    }

    #[Teardown]
    public function drop(): void
    {
        $this->db->executeStatement("DROP TABLE IF EXISTS ${this->table()};");
    }
    
    private function table(): string
    {
        return sprintf(
            'projection_%s_%s', 
            $this->projectionName(), 
            $this->projectionVersion()
        );
    }
}
```

!!! note

    You can find out more about projections [here](projection.md).

## Processor

In our example we also want to email the head office as soon as a guest is checked in.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;

final class SendCheckInEmailProcessor
{
    public function __construct(
        private readonly Mailer $mailer
    ) {
    }

    #[Subscribe(GuestIsCheckedIn::class)]
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

```php
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;

$connection = DriverManager::getConnection([
    'url' => 'mysql://user:secret@localhost/app'
]);

$mailer = /* your own mailer */;

$serializer = DefaultEventSerializer::createFromPaths(['src/Domain/Hotel/Event']);
$aggregateRegistry = (new AttributeAggregateRootRegistryFactory)->create(['src/Domain/Hotel']);

$store = new DoctrineDbalStore(
    $connection,
    $serializer,
    $aggregateRegistry
);

$hotelProjector = new HotelProjector($connection);

$projectorRepository = new ProjectorRepository([
    $hotelProjector,
]);

$projectionStore = new DoctrineStore($connection);

$projectionist = new DefaultProjectionist(
    $store,
    $projectionStore,
    $projectorRepository
);

$eventBus = SyncProjectionistEventBusWrapper::createWithDefaultLockStrategy(
    DefaultEventBus::create([
        new SyncProjectorListener($projectionist),
    ]),
    $projectionist
);

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRegistry,
    $store,
    SyncProjectionistEventBusWrapper::createWithDefaultLockStrategy(
        $eventBus,
        $projectionist
    )
);

$hotelRepository = $repositoryManager->get(Hotel::class);
```

!!! note

    You can find out more about stores [here](store.md).

## Database setup

So that we can actually write the data to a database,
we need the associated schema and databases.

```php
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    new ChainSchemaConfigurator([
        $store,
        $projectionStore
    ])
);

$schemaDirector->create();
$projectionist->boot();
```

!!! note

    you can use the predefined [cli commands](cli.md) for this.

## Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;

$hotel1 = Hotel::create(Uuid::v7(), 'HOTEL');
$hotel1->checkIn('David');
$hotel1->checkIn('Daniel');
$hotel1->checkOut('David');

$hotelRepository->save($hotel1);

$hotel2 = $hotelRepository->load(Uuid::fromString('d0d0d0d0-d0d0-d0d0-d0d0-d0d0d0d0d0d0'));
$hotel2->checkIn('David');
$hotelRepository->save($hotel2);

$hotels = $hotelProjection->getHotels();
```

!!! note

    You can also use other forms of IDs such as uuid version 6 or a custom format. 
    You can find more about this [here](aggregate_id.md).

## Result

!!! success

    We have successfully implemented and used event sourcing.

    Feel free to browse further in the documentation for more detailed information. 
    If there are still open questions, create a ticket on Github and we will try to help you.