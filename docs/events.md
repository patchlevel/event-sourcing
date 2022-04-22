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

> :warning: The payload must be serializable and unserializable as json.

> :book: An event should be named in the past because it has already happened.

Best practice is to prefix the event names with the aggregate name, lowercase everything, and replace spaces with underscores.
Here are some examples:

* profile.created
* profile.name_changed
* hotel.guest_checked_out

## Serializer

So that the events can be saved in the database, they must be serialized and deserialized.
That's what the serializer is for. 
The library comes with a `JsonSerializer` that can be given further instructions using attributes.

```php
use Patchlevel\EventSourcing\Serializer\JsonSerializer;

$serializer = JsonSerializer::createDefault(['src/Domain']);
```

The serializer needs the path information where the event classes are located 
so that it can instantiate the correct classes. 
Internally, an EventRegistry is used, which will be described later.

### Normalizer

Sometimes you also want to add more complex data as a payload. For example DateTime or value objects.
You can do that too. However, you must define a normalizer for this 
so that the library knows how to write this data to the database and load it again.
In our example we build a Name Value Object:

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

And for that we need our own normalizer. 
This normalizer must implement the `Normalizer` interface. 
You also need to implement a `normalize` and `denormalize` method.
The important thing is that the result of Normalize is serializable.

```php
use Patchlevel\EventSourcing\Serializer\Normalizer;

class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof Name) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ?Name
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return new Name($value);
    }
}
```

We can use all of this with the `Normalize` attribute as follows. 
The attribute must be set over the property to which it is to be applied.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[Normalize(NameNormalizer::class)]
        public readonly Name $name
    ) {}
}
```

In the example we simply specified the class. But we can also instantiate the normalizer and pass parameters.
That doesn't make sense at this point, but here's the example:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[Normalize(new NameNormalizer('foo'))]
        public readonly Name $name
    ) {}
}
```

> :warning: [new initializers](https://stitcher.io/blog/php-81-new-in-initializers) works only from php 8.1

### Serialized Name

By default the property name is used to name the field in json. 
This can be customized with the `SerializedName` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SerializedName;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[SerializedName('profile_name')]
        public readonly string $name
    ) {}
}
```

The whole thing looks like this

```json
{
  "profile_name": "David"
}
```

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
