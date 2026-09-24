# Upcasting

There are cases where we already have events in our stream but there is data missing
or not in the right format for our new usecase. Normally you would need to create versioned events for this.
This can lead to many versions of the same event which could lead to some chaos.
To prevent this we use the upcasting feature of the [hydrator](https://github.com/patchlevel/hydrator).
An `Upcaster` operates on the payload before it is denormalized to an event object.

## Adjust payload

Let's assume we have an `ProfileCreated` event which holds an email.
Now the business needs to have all emails to be in lower case.
For that we could adjust the aggregate and the projections to take care of that.
Or we can do this beforehand so we don't need to maintain two different places.

```php
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function upcast(ClassMetadata $metadata, array $data, array $context): array
    {
        // ignore if other event is processed
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
:::warning
Keep in mind that all hydrated classes are passed to the upcaster, so an early return for unrelated classes is recommended.
:::

For simple cases you can use the `CallbackUpcaster`, which only gets called for the given class.

```php
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;

$upcaster = CallbackUpcaster::forClass(
    ProfileCreated::class,
    static function (array $data): array {
        $data['email'] = strtolower($data['email']);

        return $data;
    },
);
```
## Adjust event name

Sometimes your event name was not the best choice and you want to change it.
Upcasters work on the payload of an already resolved event class, so they can't change the event name.
Use [aliases](events.md#alias) instead, the old name will still be resolved to the new event class.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event(name: 'profile.registered', aliases: ['profile.created'])]
final class ProfileRegistered
{
}
```
If the payload changed together with the name, the upcaster needs to know under which name the event was stored.
The serializer passes it in the context as `DefaultEventSerializer::CONTEXT_EVENT_NAME`,
the resolved class is available as `DefaultEventSerializer::CONTEXT_EVENT_CLASS`.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;

$upcaster = CallbackUpcaster::forClass(
    ProfileRegistered::class,
    static function (array $data, array $context): array {
        if ($context[DefaultEventSerializer::CONTEXT_EVENT_NAME] !== 'profile.created') {
            return $data;
        }

        $data['registeredAt'] = $data['createdAt'];
        unset($data['createdAt']);

        return $data;
    },
);
```
## Configure

After we have defined the upcasting rules, we have to register them in the hydrator with the `UpcastExtension`
and pass the hydrator to the serializer.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new UpcastExtension(
        beforeTransform: [
            new ProfileCreatedEmailLowerCastUpcaster(),
        ],
    ))
    ->build();

$serializer = DefaultEventSerializer::createFromPaths(
    ['src/Domain'],
    $hydrator,
);
```
The `UpcastExtension` has two stages where upcasters can be registered.
Upcasters in `beforeEncoding` get the raw stored payload, before any values are decoded,
for example before [personal data](sensitive-data.md) is decrypted.
This is the right place to rename or move fields.
Upcasters in `beforeTransform` run right before the object is built and see the decoded values.
Use this stage if you want to change the value of an encrypted field.

:::tip
The snapshot store uses its own hydrator. If you need upcasting for snapshots as well,
pass a hydrator with the `UpcastExtension` to the [snapshot store](snapshots.md) too.
:::

## Learn more

* [How to create messages](message.md)
* [How to define events](events.md)
* [How to configure store](store.md)
* [How to use the hydrator](https://github.com/patchlevel/hydrator)
