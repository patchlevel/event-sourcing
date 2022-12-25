# Aggregate

!!! abstract

    Aggregate is a pattern in Domain-Driven Design. A DDD aggregate is a cluster of domain objects 
    that can be treated as a single unit. [...]

    [DDD Aggregate - Martin Flower](https://martinfowler.com/bliki/DDD_Aggregate.html) 

An Aggregate has to inherit from `AggregateRoot` and need to implement the method `aggregateRootId`.
`aggregateRootId` is the identifier from `AggregateRoot` like a primary key for an entity.
The events will be added later, but the following is enough to make it executable:

To register an aggregate you have to set the `Aggregate` attribute over the class,
otherwise it will not be recognized as an aggregate.
There you also have to give the aggregate a name.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public static function register(string $id): self 
    {
        $self = new self();
        // todo: record create event
        
        return $self;
    }
}
```

!!! warning

    The aggregate is not yet finished and has only been built to the point that you can instantiate the object.

!!! tip

    An aggregateId can be an **uuid**, you can find more about this [here](./uuid.md).

We use a so-called named constructor here to create an object of the AggregateRoot.
The constructor itself is protected and cannot be called from outside.
But it is possible to define different named constructors for different use-cases like `import`.

After the basic structure for an aggregate is in place, it could theoretically be saved:

```php
use Patchlevel\EventSourcing\Repository\Repository;

final class CreateProfileHandler
{
    public function __construct(
        private readonly Repository $profileRepository
    ) {}
    
    public function __invoke(CreateProfile $command): void
    {
        $profile = Profile::register($command->id());
        
        $this->profileRepository->save($profile);
    }
}
```

!!! warning

    If you look in the database now, you would see that nothing has been saved.
    This is because only events are stored in the database and as long as no events exist,
    nothing happens.

!!! note

    A **command bus** system is not necessary, only recommended.
    The interaction can also easily take place in a controller or service.

## Create a new aggregate

In order that an aggregate is actually saved, at least one event must exist in the DB.
For our aggregate we create the Event `ProfileRegistered`:

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.registered')]
final class ProfileRegistered
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $name
    ) {}
}
```

!!! note

    You can find out more about events [here](./events.md).

After we have defined the event, we have to adapt the creation of the profile:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function register(string $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileRegistered($id, $name));

        return $self;
    }
    
    #[Apply]
    protected function applyProfileRegistered(ProfileRegistered $event): void 
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }
}
```

!!! tip

    Prefixing the apply methods with "apply" improves readability.

In our named constructor `register` we have now created the event and recorded it with the method `recordThat`.
The aggregate remembers all new recorded events in order to save them later.
At the same time, a defined apply method is executed directly so that we can change our state.

So that the AggregateRoot also knows which method it should call, 
we have to mark it with the `Apply` [attributes](https://www.php.net/manual/en/language.attributes.overview.php).
We did that in the `applyProfileRegistered` method.
In this method we change the `Profile` properties `id` and `name` with the transferred values.

### Modify an aggregate

In order to change the state of the aggregates afterwards, only further events have to be defined.
As example we can add a `NameChanged` event:

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public readonly string $name
    ) {
    }
}
```

!!! note

    Events should best be written in the past, as they describe a state that has happened.

After we have defined the event, we can define a new public method called `changeName` to change the profile name. 
This method then creates the event `NameChanged` and records it:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function register(string $id, string $name): static
    {
        $self = new static();
        $self->recordThat(new ProfileRegistered($id, $name));

        return $self;
    }
    
    public function changeName(string $name): void 
    {
        $this->recordThat(new NameChanged($name));
    }
    
    #[Apply]
    protected function applyProfileRegistered(ProfileRegistered $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }
    
    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }
}
```

We have also defined a new `apply` method named `applyNameChanged` 
where we change the name depending on the value in the event.

When using it, it can look like this:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;

final class ChangeNameHandler
{
    private Repository $profileRepository;

    public function __construct(Repository $profileRepository) 
    {
        $this->profileRepository = $profileRepository;
    }
    
    public function __invoke(ChangeName $command): void
    {
        $profile = $this->profileRepository->load($command->id());
        $profile->changeName($command->name());
    
        $this->profileRepository->save($profile);
    }
}
```

!!! note

    You can read more about Repository [here](./repository.md).

Here the aggregate is loaded from the `repository` by fetching all events from the database.
These events are then executed again with the `apply` methods in order to rebuild the current state.
All of this happens automatically in the `load` method.

The method `changeName` is then executed on the aggregate to change the name.
In this method the event `NameChanged` is generated and recorded.
The `applyNameChanged` method was also called again internally to adjust the state.

When the `save` method is called on the repository, 
all newly recorded events are then fetched and written to the database.
In this specific case only the `NameChanged` changed event.

## Multiple apply attributes on the same method

You can also define several apply attributes with different events using the same method.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    // ...
    
    #[Apply(ProfileCreated::class)]
    #[Apply(NameChanged::class)]
    protected function applyProfileCreated(ProfileCreated|NameChanged $event): void
    {
        if ($event instanceof ProfileCreated) {
            $this->id = $event->profileId;
        }
        
        $this->name = $event->name;
    }
}
```

## Suppress missing apply methods

Sometimes you have events that do not change the state of the aggregate itself, 
but are still recorded for the future, to listen on it or to create a projection. 
So that you are not forced to write an apply method for it, 
you can suppress the missing apply exceptions these events with the `SuppressMissingApply` attribute.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate('profile')]
#[SuppressMissingApply([NameChanged::class])]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    // ...
    
    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }
}
```

## Suppress missing apply for all methods

You can also completely deactivate the exceptions for missing apply methods.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate('profile')]
#[SuppressMissingApply(SuppressMissingApply::ALL)]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    // ...
    
    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }
}
```

!!! warning

    When all events are suppressed, debugging becomes more difficult if you forget an apply method.

## Business rules

Usually, aggregates have business rules that must be observed. Like there may not be more than 10 people in a group.

These rules must be checked before an event is recorded. 
As soon as an event was recorded, the described thing happened and cannot be undone.

A further check in the apply method is also not possible because these events have already happened 
and were then also saved in the database. 

In the next example we want to make sure that **the name is at least 3 characters long**:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;
    
    // ...
    
    public function name(): string 
    {
        return $this->name;
    }
    
    public function changeName(string $name): void 
    {
        if (strlen($name) < 3) {
            throw new NameIsToShortException($name);
        }
    
        $this->recordThat(new NameChanged($name));
    }
    
    #[Apply]
    protected function applyNameChanged(NameChanged $event): void 
    {
        $this->name = $event->name();
    }
}
```

!!! danger

    Disregarding this can break the rebuilding of the state!

We have now ensured that this rule takes effect when a name is changed with the method `changeName`. 
But when we create a new profile this rule does not currently apply.

In order for this to work, we either have to duplicate the rule or outsource it. 
Here we show how we can do it all with a value object:

```php
final class Name
{
    private string $value;
    
    public function __construct(string $value) 
    {
        if (strlen($value) < 3) {
            throw new NameIsToShortException($value);
        }
        
        $this->value = $value;
    }
    
    public function toString(): string 
    {
        return $this->value;
    }
}
```

We can now use the value object `Name` in our aggregate:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private Name $name;
    
    public static function register(string $id, Name $name): static
    {
        $self = new static();
        $self->recordThat(new ProfileRegistered($id, $name));

        return $self;
    }
    
    // ...
    
    public function name(): Name 
    {
        return $this->name;
    }
    
    public function changeName(Name $name): void 
    {
        $this->recordThat(new NameChanged($name));
    }
    
    #[Apply]
    protected function applyNameChanged(NameChanged $event): void 
    {
        $this->name = $event->name;
    }
}
```

In order for the whole thing to work, we still have to adapt our `NameChanged` event, 
since we only expected a string before but now passed a `Name` value object.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[NameNormalizer]
        public readonly Name $name
    ) {}
}
```
!!! warning

    The payload must be serializable and unserializable as json.

!!! note

    You can find out more about event normalizer [here](./events.md#normalizer).

There are also cases where business rules have to be defined depending on the aggregate state.
Sometimes also from states, which were changed in the same method.
This is not a problem, as the `apply` methods are always executed immediately.

In the next case we throw an exception if the hotel is already overbooked.
Besides that, we record another event `FullyBooked`, if the hotel is fully booked with the last booking. 
With this event we could [notify](./processor.md) external systems 
or fill a [projection](./projection.md) with fully booked hotels.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate('hotel')]
#[SuppressMissingApply([FullyBooked::class])]
final class Hotel extends AggregateRoot
{
    private const SIZE = 5;

    private int $people;
    
    // ...
    
    public function book(string $name): void 
    {
        if ($this->people === self::SIZE) {
            throw new NoPlaceException($name);
        }
        
        $this->recordThat(new RoomBocked($name));
        
        if ($this->people === self::SIZE) {
            $this->recordThat(new FullyBooked());
        }
    }
    
    #[Apply]
    protected function applyRoomBocked(RoomBocked $event): void 
    {
        $this->people++;
    }
}
```

## Working with dates

An aggregate should always be deterministic. In other words, whenever I execute methods on the aggregate, 
I always get the same result. This also makes testing much easier.

But that often doesn't seem to be possible, e.g. if you want to save a createAt date. 
But you can pass this information by yourself.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private Name $name;
    private DateTimeImmutable $registeredAt;
    
    public static function register(string $id, string $name, DateTimeImmutable $registeredAt): static
    {
        $self = new static();
        $self->recordThat(new ProfileRegistered($id, $name, $registeredAt));

        return $self;
    }
    
    // ...
}
```

But if you still want to make sure that the time is "now" and not in the past or future, you can pass a clock.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Clock\Clock;

#[Aggregate('profile')]
final class Profile extends AggregateRoot
{
    private string $id;
    private Name $name;
    private DateTimeImmutable $registeredAt;
    
    public static function register(string $id, string $name, Clock $clock): static
    {
        $self = new static();
        $self->recordThat(new ProfileRegistered($id, $name, $clock->now()));

        return $self;
    }
    
    // ...
}
```

Now you can pass the `SystemClock` to determine the current time. 
Or for test purposes the `FrozenClock`, which always returns the same time.

!!! note

    You can find out more about clock [here](./clock.md).

## Aggregate Root Registry

The library needs to know about all aggregates so that the correct aggregate class is used to load from the database.
There is an `AggregateRootRegistry` for this purpose. The registry is a simple hashmap between aggregate name and aggregate class.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;

$aggregateRegistry = new AggregateRootRegistry([
    'profile' => Profile::class
]);
```

So that you don't have to create it by hand, you can use a factory.
By default, the `AttributeAggregateRootRegistryFactory` is used.
There, with the help of paths, all classes with the attribute `Aggregate` are searched for
and the `AggregateRootRegistry` is built up.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;

$aggregateRegistry = (new AttributeEventRegistryFactory())->create($paths);
```