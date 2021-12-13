[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/master)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing/license)](//packagist.org/packages/patchlevel/event-sourcing)

# event-sourcing

Small lightweight event-sourcing library.

## installation

```
composer require patchlevel/event-sourcing
```

## integration

* [Symfony](https://github.com/patchlevel/event-sourcing-bundle)
* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## documentation

* [Aggregate](docs/aggregate.md)
* [Repository](docs/repository.md)
* [Event Bus](docs/event_bus.md)
* [Processor](docs/processor.md)
* [Projection](docs/projection.md)
* [Snapshots](docs/snapshots.md)
* [Pipeline](docs/pipeline.md)
* [Tests](docs/tests.md)
* [FAQ](docs/faq.md)

## Getting Started


### define some events

```php
<?php declare(strict_types=1);

namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class HotelCreated extends AggregateChanged
{
    public static function raise(string $id, string $hotelName): self 
    {
        return new self($id, ['hotelId' => $id, 'hotelName' => $hotelName]);
    }

    public function hotelId(): string
    {
        return $this->aggregateId;
    }

    public function hotelName(): string
    {
        return $this->payload['hotelName'];
    }
}
```

```php
<?php declare(strict_types=1);

namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class GuestIsCheckedIn extends AggregateChanged
{
    public static function raise(string $id, string $guestName): self 
    {
        return new self($id, ['guestName' => $guestName]);
    }

    public function guestName(): string
    {
        return $this->payload['guestName'];
    }
}
```

```php
<?php declare(strict_types=1);

namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class GuestIsCheckedOut extends AggregateChanged
{
    public static function raise(string $id, string $guestName): self 
    {
        return new self($id, ['guestName' => $guestName]);
    }

    public function guestName(): string
    {
        return $this->payload['guestName'];
    }
}
```

### define aggregates

```php
<?php declare(strict_types=1);

namespace App\Domain\Hotel;

use App\Domain\Profile\Event\MessagePublished;
use App\Domain\Profile\Event\ProfileCreated;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

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

    public static function create(string $id, string $hotelName): self
    {
        $self = new self();
        $self->record(HotelCreated::raise($id, $hotelName));

        return $self;
    }

    public function checkIn(string $guestName): void
    {
        if (in_array($guestName, $this->guests, true)) {
            throw new GuestHasAlreadyCheckedIn($guestName);
        }
    
        $this->record(GuestIsCheckedIn::raise($this->id, $guestName));
    }
    
    public function checkOut(string $guestName): void
    {
        if (!in_array($guestName, $this->guests, true)) {
            throw new IsNotAGuest($guestName);
        }
    
        $this->record(GuestIsCheckedOut::raise($this->id, $guestName));
    }
    
    
    protected function apply(AggregateChanged $event): void
    {
        if ($event instanceof HotelCreated) {
            $this->id = $event->hotelId();
            $this->name = $event->hotelName();
            $this->guests = [];
            
            return;
        } 
        
        if ($event instanceof GuestIsCheckedIn) {
            $this->guests[] = $event->guestName();
            
            return;
        }
        
        if ($event instanceof GuestIsCheckedOut) {
            $this->guests = array_values(
                array_filter(
                    $this->guests,
                    fn ($name) => $name !== $event->guestName();
                )
            );
            
            return;
        }
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
```

> :book: 

### define projections

```php
<?php declare(strict_types=1);

namespace App\Projection;

use Doctrine\DBAL\Connection;
use App\Infrastructure\MongoDb\MongoDbManager;
use Patchlevel\EventSourcing\Projection\Projection;

final class HotelProjection implements Projection
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function getHandledMessages(): iterable
    {
        yield HotelCreated::class => 'applyHotelCreated';
        yield GuestIsCheckedIn::class => 'applyGuestIsCheckedIn';
        yield GuestIsCheckedOut::class => 'applyGuestIsCheckedOut';
    }

    public function applyHotelCreated(HotelCreated $event): void
    {
        $this->db->insert(
            'hotel', 
            [
                'id' => $event->hotelId(), 
                'name' => $event->hotelName(),
                'guests' => 0
            ]
        );
    }
    
    public function applyGuestIsCheckedIn(GuestIsCheckedIn $event): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests + 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    public function applyGuestIsCheckedOut(GuestIsCheckedOut $event): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests - 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    public function create(): void
    {
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS hotel (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);');
    }

    public function drop(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS hotel;');
    }
}
```

### configuration

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Store\SingleTableStore;

$hotelProjection = new HotelProjection($this->connection);
$projectionRepository = new DefaultProjectionRepository(
    [$hotelProjection]
);

$eventBus = new DefaultEventBus();
$eventBus->addListener(new ProjectionListener($projectionRepository));

$store = new SingleTableStore(
    $this->connection,
    [Hotel::class => 'hotel'],
    'eventstore'
);

$hotelRepository = new DefaultRepository($store, $eventBus, Hotel::class);
```

### database setup

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;

(new DoctrineSchemaManager())->create($store);
$hotelProjection->create();
```

### usage

```php
$hotel = Hotel::create('1', 'HOTEL');
$hotel->checkIn('David');
$hotel->checkIn('Daniel');
$hotel->checkOut('David');

$hotelRepository->save($hotel);
```