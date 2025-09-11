# Dynamic Consistency Boundary

??? example "Experimental"

    This feature is still experimental and may change in the future.
    Use it with caution.
    
Dynamic Consistency Boundary (DCB) is an event‑sourcing approach for making consistent,
cross‑stream decisions without loading full aggregates.
For each decision, it builds a minimal, purpose‑built state from a targeted subset of events selected via tags.
Lightweight projections compute just the values needed to validate commands and derive new events.
The decision evaluation and event append are coupled by an optimistic append condition to prevent race conditions;
if the queried subset changes concurrently, the write is rejected and can be retried.
This makes handlers simple, fast, and scalable, since only relevant events are processed.
DCB is a great fit when business rules span multiple streams.

!!! note

    You can read more about Dynamic Consistency Boundary on page [dcb.events](https://dcb.events/).
    
Since this approach differs slightly from the standard "aggregate" event sourcing principle,
we will use the [Getting Started](./getting_started.md) example and build it as a DCB variant.

In our little getting started example, we manage hotels.
We keep the example small, so we can only create hotels and let guests check in and check out.

## Define some events

First we define the events that happen in our system.

A hotel can be created with an ID and a name. In DCB we also tag the hotelId so projections can filter by this hotel.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[EventTag(prefix: 'hotel')]
        public readonly Uuid $hotelId,
        public readonly string $hotelName,
    ) {
    }
}
```
A guest can check in.
We tag the hotelId and the guest name so projections can filter by this combination.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Event('hotel.guest_checked_in')]
final class GuestIsCheckedIn
{
    public function __construct(
        #[EventTag(prefix: 'hotel')]
        public readonly Uuid $hotelId,
        #[EventTag(prefix: 'guest')]
        public readonly string $guestName,
    ) {
    }
}
```
A guest can check out again. Here we tag the hotelId and the guest name again.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Event('hotel.guest_checked_out')]
final class GuestIsCheckedOut
{
    public function __construct(
        #[EventTag(prefix: 'hotel')]
        public readonly Uuid $hotelId,
        #[EventTag(prefix: 'guest')]
        public readonly string $guestName,
    ) {
    }
}
```
!!! note

    You can find out more about events [here](events.md).    
    
## Define Commands

Unlike in the [Getting Started](./getting_started.md) section, we're working with the [Command Bus](./command_bus.md) here.
This allows us to express our interaction with the system using commands. We can do the following with our system:

The following command creates a new hotel. It carries the hotel ID and name.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;

class CreateHotel
{
    public function __construct(
        public Uuid $hotelId,
        public readonly string $hotelName,
    ) {
    }
}
```
Next, this command checks a guest in to a specific hotel.
It contains the hotel ID and the guest name.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;

class CheckIn
{
    public function __construct(
        public Uuid $hotelId,
        public readonly string $guestName,
    ) {
    }
}
```
Last but not least, this command checks a guest out of a specific hotel.
It also provides the hotel ID and guest name.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;

class CheckOut
{
    public function __construct(
        public Uuid $hotelId,
        public readonly string $guestName,
    ) {
    }
}
```
## Define projections

With DCB we don’t load an aggregate.
Instead, we assemble the minimal state for a single decision from lightweight projections.

Each projection:

* Declares an initial state
* Applies only the few events relevant to compute the decision value
* Optionally filters events by tags

The first projection answers only whether the hotel already exists.

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class HotelExists extends BasicProjection
{
    public function __construct(
        private readonly Uuid $hotelId,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["hotel:{$this->hotelId->toString()}"];
    }

    #[Apply]
    public function applyHotelCreated(bool $state, HotelCreated $event): bool
    {
        return true;
    }
}
```
The second projection counts the guests currently checked in.

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class NumberOfGuestsInHotel extends BasicProjection
{
    public function __construct(
        private readonly Uuid $hotelId,
    ) {
    }

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["hotel:{$this->hotelId->toString()}"]; // same tag as above
    }

    #[Apply]
    public function applyGuestIsCheckedIn(int $state, GuestIsCheckedIn $event): int
    {
        return $state + 1;
    }

    #[Apply]
    public function applyGuestIsCheckedOut(int $state, GuestIsCheckedOut $event): int
    {
        return $state - 1;
    }
}
```
The third projection answers whether the given guest is already checked into this hotel.

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class GuestAlreadyCheckedIn extends BasicProjection
{
    public function __construct(
        private readonly Uuid $hotelId,
        private readonly string $guestName,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return [
            "hotel:{$this->hotelId->toString()}",
            "guest:{$this->guestName}",
        ];
    }

    #[Apply]
    public function applyGuestIsCheckedIn(bool $state, GuestIsCheckedIn $event): bool
    {
        return true;
    }

    #[Apply]
    public function applyGuestIsCheckedOut(bool $state, GuestIsCheckedOut $event): bool
    {
        return false;
    }
}
```
## Define handlers

We’ll implement three command handlers corresponding to our commands.

First, we implement the handler for the `CreateHotel` command.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;

final class CreateHotelHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(CreateHotel $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'hotelExists' => new HotelExists($command->hotelId),
        ]);

        if ($state['hotelExists']) {
            throw new RuntimeException('Hotel already exists');
        }

        $this->eventAppender->append([
            new HotelCreated($command->hotelId, $command->hotelName),
        ], $state->appendCondition);
    }
}
```
!!! note

    Handlers build a Decision Model from the projections and then append events with an optimistic AppendCondition. 
    If any relevant event arrives between read and write, the append fails and you can retry.
    
The next handler implements the `CheckIn` command.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;

final class CheckInHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(CheckIn $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'hotelExists' => new HotelExists($command->hotelId),
            'guestCount' => new NumberOfGuestsInHotel($command->hotelId),
            'alreadyIn' => new GuestAlreadyCheckedIn($command->hotelId, $command->guestName),
        ]);

        if (!$state['hotelExists']) {
            throw new RuntimeException('Hotel does not exist');
        }

        if ($state['alreadyIn']) {
            throw new RuntimeException(sprintf('Guest "%s" already checked in', $command->guestName));
        }

        // Optional policy example: max 5 guests
        if ($state['guestCount'] >= 5) {
            throw new RuntimeException('Hotel is full');
        }

        $this->eventAppender->append([
            new GuestIsCheckedIn($command->hotelId, $command->guestName),
        ], $state->appendCondition);
    }
}
```
And the last handler implements the `CheckOut` command.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;

final class CheckOutHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(CheckOut $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'hotelExists' => new HotelExists($command->hotelId),
            'alreadyIn' => new GuestAlreadyCheckedIn($command->hotelId, $command->guestName),
        ]);

        if (!$state['hotelExists']) {
            throw new RuntimeException('Hotel does not exist');
        }

        if (!$state['alreadyIn']) {
            throw new RuntimeException(sprintf('Guest "%s" is not checked in', $command->guestName));
        }

        $this->eventAppender->append([
            new GuestIsCheckedOut($command->hotelId, $command->guestName),
        ], $state->appendCondition);
    }
}
```
## Configuration

Now we can wire everything together.

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;

$connection = DriverManager::getConnection((new DsnParser())->parse('pdo-pgsql://user:secret@localhost/app'));
$eventRegistry = (new AttributeEventRegistryFactory())->create(['src/Domain/Hotel/Event']);
$serializer = new DefaultEventSerializer($eventRegistry);

$eventStore = new TaggableDoctrineDbalStore($connection, $serializer, $eventRegistry);

$decisionModelBuilder = new StoreDecisionModelBuilder($eventStore);
$eventAppender = new StoreEventAppender($eventStore);

$provider = new ServiceHandlerProvider([
    new CreateHotelHandler($decisionModelBuilder, $eventAppender),
    new CheckInHandler($decisionModelBuilder, $eventAppender),
    new CheckOutHandler($decisionModelBuilder, $eventAppender),
]);

$commandBus = new SyncCommandBus($provider);
```
## Database setup

The last step is to create the database schema.

```php
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    new ChainDoctrineSchemaConfigurator([$eventStore]),
);
$schemaDirector->create();
```
## Usage

Now we can use our command bus to execute our commands.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;

$hotelId = Uuid::generate();
$commandBus->dispatch(new CreateHotel($hotelId, 'HOTEL'));
$commandBus->dispatch(new CheckIn($hotelId, 'David'));
$commandBus->dispatch(new CheckIn($hotelId, 'Daniel'));
$commandBus->dispatch(new CheckOut($hotelId, 'David'));
```
## Conclusion

We've seen how to use DCB to make decisions consistently.
In this example we skipped the subscription part,
but you can add it by following the [Getting Started](./getting_started.md) section.

## Learn more

* [Events](./events.md)
* [Command Bus](./command_bus.md)
* [Store](./store.md)
