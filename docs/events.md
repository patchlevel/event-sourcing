# Events

Events are used to describe things that happened in the application.
Since the events already happened, they are also immutable.
In event sourcing, these are used to save and rebuild the current state.
You can also listen on events to react and perform different actions.

An event has a name and additional information called payload.
Such an event can be represented as any class.
It is important that the payload can be serialized as JSON at the end.
How to ensure this for complex values is shown in the [normalizer](#normalizer) section below.

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
        public readonly string $name,
    ) {
    }
}
```
:::warning
The payload must be serializable and unserializable as json.
:::

:::tip
An event should be named in the past because it has already happened.

Best practice is to prefix the event names with the aggregate name, lowercase everything, and replace spaces with underscores.
Here are some examples:

* `profile.created`
* `profile.name_changed`
* `hotel.guest_checked_out`
  
:::

## Alias

You also have the option to set aliases for the events.
This can be useful when you want to rename events but still need to process the old ones.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event(name: 'profile.registered', aliases: ['profile.created'])]
final class ProfileRegistered
{
}
```
When saving, the name will always be used. However, when loading, aliases will also be taken into account.

:::note
In the database, the name of the event is always stored,
allowing the class to be renamed without encountering any issues.
:::

:::tip
If you want to make significant changes to an event,
you can take a look at the [Upcaster](upcasting.md).
:::

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
Internally, an EventRegistry is used, which is described in the [Event Registry](#event-registry) section below.

## Encoder

The serializer turns an event into an array first and then encodes that array into a string.
The encoding is done by an `Encoder`. By default the `JsonEncoder` is used, which encodes the payload as JSON.

If you want to change how the payload is encoded, you can pass your own encoder to the serializer.

```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;

$serializer = new DefaultEventSerializer(
    (new AttributeEventRegistryFactory())->create(['src/Domain']),
    encoder: new JsonEncoder(),
);
```
The `Encoder` interface has two methods:

```php
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;

final class MyEncoder implements Encoder
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function encode(array $data, array $options = []): string
    {
        // your encoding
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function decode(string $data, array $options = []): array
    {
        // your decoding
    }
}
```
An `encode` call that fails must throw an `EncodeNotPossible` exception,
a failing `decode` call a `DecodeNotPossible` exception.

The options are passed through from the serializer. The `JsonEncoder` understands
`Encoder::OPTION_PRETTY_PRINT`, which is used by the [cli](cli.md) to print readable payloads.

```php
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;

/** @var EventSerializer $serializer */
$data = $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);
```
:::warning
The encoder decides the format of everything that is already in your store.
If you change it, old events can no longer be decoded.
:::

## Normalizer

Sometimes you also want to add more complex data as a payload. For example DateTime or value objects.
You can do that too. However, you must define a normalizer for this
so that the library knows how to write this data to the database and load it again.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        public readonly Uuid $id,
        #[NameNormalizer]
        public readonly Name $name,
        #[DateTimeImmutableNormalizer]
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
```
:::tip
Built-in normalizers like `IdNormalizer` and `DateTimeImmutableNormalizer` can be inferred from the type hint
and so you don't have to specify them. If you want to configure the Normalizer, you still have to do it.
:::

:::note
You can find out more about [normalizer](normalizer.md).
:::

## Event Registry

The library needs to know about all events
so that the correct event class is used for the serialization and deserialization of an event.
There is an EventRegistry for this purpose. The registry is a simple hashmap between event name and event class.

```php
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;

$eventRegistry = new EventRegistry([
    'profile.created' => ProfileCreated::class,
]);
```
So that you don't have to create it by hand, you can use a factory.
By default, the `AttributeEventRegistryFactory` is used.
There, with the help of paths, all classes with the attribute `Event` are searched for
and the `EventRegistry` is built up.

```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;

$eventRegistry = (new AttributeEventRegistryFactory())->create([/* paths... */]);
```
:::tip
Scanning the paths on every request costs time. In production you can wrap the factory
in a [metadata cache](metadata-cache.md).
:::

## Learn more

* [How to normalize events](normalizer.md)
* [How to subscribe on events](subscription.md)
* [How to store events](store.md)
* [How to upcast events](upcasting.md)
* [How to use messages](message.md)
* [How to cache metadata](metadata-cache.md)
