[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2F1.3.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/1.3.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# Event-Sourcing

A lightweight but also all-inclusive event sourcing library with a focus on developer experience.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](docs/snapshots.md) system to quickly rebuild the aggregates
* [Pipeline](docs/pipeline.md) to build new [projections](docs/projection.md) or to migrate events
* [Scheme management](docs/store.md) and [doctrine migration](docs/store.md) support
* Dev [tools](docs/tools.md) such as a realtime event watcher
* Built in [cli commands](docs/cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing
```

## Documentation

* [Events](docs/events.md)
* [Aggregate](docs/aggregate.md)
* [Repository](docs/repository.md)
* [Event Bus](docs/event_bus.md)
* [Processor](docs/processor.md)
* [Projection](docs/projection.md)
* [Snapshots](docs/snapshots.md)
* [Store](docs/store.md)
* [Pipeline](docs/pipeline.md)
* [Tests](docs/tests.md)
* [Tools](docs/tools.md)
* [CLI](docs/cli.md)
* [FAQ](docs/faq.md)

## Integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## Getting Started

In our little getting started example, we manage hotels. 
We keep the example small, so we can only create hotels and let guests check in and check out.

### Define some events

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

> :book: You can find out more about events [here](./docs/events.md).

### Define aggregates

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

> :book: You can find out more about aggregates [here](./docs/aggregate.md).

### Define projections

So that we can see all the hotels on our website and also see how many guests are currently visiting the hotels, 
we need a projection for it.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection;

final class HotelProjection implements Projection
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    #[Handle(HotelCreated::class)]
    public function handleHotelCreated(HotelCreated $event): void
    {
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

> :book: You can find out more about projections [here](./docs/projection.md).

### Processor

In our example we also want to send an email to the head office as soon as a guest is checked in.

```php
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class SendCheckInEmailListener implements Listener
{
    private Mailer $mailer;

    private function __construct(Mailer $mailer) 
    {
        $this->mailer = $mailer;
    }

    public function __invoke(Message $message): void
    {
        $event = $message->event();
    
        if (!$event instanceof GuestIsCheckedIn) {
            return;
        }

        $this->mailer->send(
            'hq@patchlevel.de',
            'Guest is checked in',
            sprintf('A new guest named "%s" is checked in', $event->guestName)
        );
    }
}
```

> :book: You can find out more about processor [here](./docs/processor.md).

### Configuration

After we have defined everything, we still have to plug the whole thing together:

```php
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Serializer\JsonSerializer;
use Patchlevel\EventSourcing\Store\SingleTableStore;

$connection = DriverManager::getConnection([
    'url' => 'mysql://user:secret@localhost/app'
]);

$mailer = /* your own mailer */;

$hotelProjection = new HotelProjection($connection);
$projectionHandler = new DefaultProjectionHandler([
    $hotelProjection,
]);

$eventBus = new DefaultEventBus();
$eventBus->addListener(new ProjectionListener($projectionHandler));
$eventBus->addListener(new SendCheckInEmailListener($mailer));

$serializer = JsonSerializer::createDefault(['src/Domain/Hotel/Event']);
$aggregateRegistry = (new AttributeAggregateRootRegistryFactory)->create(['src/Domain/Hotel']);

$store = new SingleTableStore(
    $connection,
    $serializer,
    $aggregateRegistry,
    'eventstore'
);

$hotelRepository = new DefaultRepository($store, $eventBus, Hotel::class);
```

> :book: You can find out more about stores [here](./docs/store.md).

### Database setup

So that we can actually write the data to a database, 
we need the associated schema and databases.

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;

(new DoctrineSchemaManager())->create($store);
$projectionHandler->create();
```

> :book: you can use the predefined [cli commands](docs/cli.md) for this.

### Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
$hotel = Hotel::create('1', 'HOTEL');
$hotel->checkIn('David');
$hotel->checkIn('Daniel');
$hotel->checkOut('David');

$hotelRepository->save($hotel);

$hotel2 = $hotelRepository->load('2');
$hotel2->checkIn('David');
$hotelRepository->save($hotel2);
```

> :book: An aggregateId can be an **uuid**, you can find more about this [here](./docs/faq.md).

Consult the [documentation](#documentation) or [FAQ](./docs/faq.md) for more information.
If you still have questions, feel free to create an issue for it :)
