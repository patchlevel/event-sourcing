# Events

Events are used to describe things that happened in the application. 
Since the events already happened, they are also immnutable. 
In event sourcing, these are used to save and rebuild the current state. 
You can also listen on events to react and perform different actions.

An event has a name and additional information called payload.
Such an event can be represented as any class.
It is important that the payload can be serialized as JSON at the end. 
Later it will be explained how to ensure it for all values.

To register an event you have to set the `Event` attribute over the class, 
otherwise it will not be recognized as an event. 
There you also have to give the event a name.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event(name: 'profile.created')]
final class ProfileCreated
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $name
    ) {}
}
```

!!! warning

    The payload must be serializable and unserializable as json.

!!! tip

    An event should be named in the past because it has already happened.

    Best practice is to prefix the event names with the aggregate name, lowercase everything, and replace spaces with underscores.
    Here are some examples:

    * `profile.created`
    * `profile.name_changed`
    * `hotel.guest_checked_out`

## Serializer

So that the events can be saved in the database, they must be serialized and deserialized.
That's what the serializer is for. 
The library comes with a `DefaultEventSerializer` that can be given further instructions using attributes.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;

$serializer = DefaultEventSerializer::createFromPaths(['src/Domain']);
```

The serializer needs the path information where the event classes are located 
so that it can instantiate the correct classes. 
Internally, an EventRegistry is used, which will be described later.

## Normalizer

Sometimes you also want to add more complex data as a payload. For example DateTime or value objects.
You can do that too. However, you must define a normalizer for this 
so that the library knows how to write this data to the database and load it again.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public readonly string $name,
        #[DateTimeImmutableNormalizer]
        public readonly DateTimeImmutable $changedAt
    ) {}
}
```

!!! note

    You can find out more about normalizer [here](normalizer.md).    

## Event Registry

The library needs to know about all events 
so that the correct event class is used for the serialization and deserialization of an event.
There is an EventRegistry for this purpose. The registry is a simple hashmap between event name and event class.

```php
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;

$eventRegistry = new EventRegistry([
    'profile.created' => ProfileCreated::class
]);
```

So that you don't have to create it by hand, you can use a factory.
By default, the `AttributeEventRegistryFactory` is used. 
There, with the help of paths, all classes with the attribute `Event` are searched for 
and the `EventRegistry` is built up.

```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;

$eventRegistry = (new AttributeEventRegistryFactory())->create($paths);
```
