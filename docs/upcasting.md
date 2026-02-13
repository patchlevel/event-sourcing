# Upcasting

There are cases where we already have events in our stream but there is data missing
or not in the right format for our new usecase. Normally you would need to create versioned events for this.
This can lead to many versions of the same event which could lead to some chaos.
To prevent this we offer upcasting, which can operate on the payload before it is denormalized to an event object.

Upcasting is part of the hydrator. You write an `Upcaster`, or use the `CallbackUpcaster`,
and register it on the hydrator through the `UpcastExtension`.

## Adjust payload

Let's assume we have a `ProfileCreated` event which holds an email.
Now the business needs all emails to be in lower case.
For that we could adjust the aggregate and the projections to take care of that.
Or we can do this beforehand so we don't need to maintain two different places.

```php
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    /**
     * @param ClassMetadata<object> $metadata
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $context
     *
     * @return array<string, mixed>
     */
    public function upcast(ClassMetadata $metadata, array $data, array $context): array
    {
        // ignore if another event is processed
        if ($metadata->className !== ProfileCreated::class) {
            return $data;
        }

        if (!array_key_exists('email', $data) || !is_string($data['email'])) {
            return $data;
        }

        $data['email'] = strtolower($data['email']);

        return $data;
    }
}
```
:::tip
For simple cases you can use the `Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster`
instead of a dedicated class:

```php
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;

$upcaster = CallbackUpcaster::forClass(
    ProfileCreated::class,
    static function (array $data, array $context): array {
        $data['email'] = strtolower($data['email']);

        return $data;
    },
);
```
:::
## Adjust event name

Renaming an event through an upcaster is no longer supported, because the event class is
already resolved from the stored event name before the payload is upcasted.
Use [event aliases](events.md#alias) instead.

## Configure

After we have defined the upcasting rules, we register them on the hydrator through the
`UpcastExtension` and pass the hydrator to the serializer.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new UpcastExtension(
        beforeEncoding: [
            new ProfileCreatedEmailLowerCastUpcaster(),
        ],
    ))
    ->build();

$serializer = DefaultEventSerializer::createFromPaths(
    ['src/Domain'],
    $hydrator,
);
```
:::tip
`beforeEncoding` upcasters reshape the raw stored payload before its values are decoded.
If you need the already decoded (and decrypted) values, register them as `beforeTransform` instead.
:::
## Upcasting headers

Message headers are hydrated the same way as events, so the same upcasters work for them.
An upcaster selects the header by its class name, build a hydrator with the `UpcastExtension`
and pass it to the headers serializer.

```php
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new UpcastExtension(
        beforeEncoding: [
            new ApplicationHeaderUpcaster(),
        ],
    ))
    ->build();

$headersSerializer = DefaultHeadersSerializer::createDefault($hydrator);
```
## Learn more

* [How to create messages](message.md)
* [How to define events](events.md)
* [How to configure store](store.md)
