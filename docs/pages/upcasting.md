# Upcasting

There are cases where the already have events in our stream but there is data missing
or not in the right format for our new usecase. Normally you would need to create versioned events for this.
This can lead to many versions of the same event which could lead to some chaos.
To prevent this we offer `Upcaster`, which can operate on the payload before denormalizing to an event object.
There you can change the event name and adjust the payload of the event.

## Adjust payload

Let's assume we have an `ProfileCreated` event which holds an email.
Now the business needs to have all emails to be in lower case.
For that we could adjust the aggregate and the projections to take care of that.
Or we can do this beforehand so we don't need to maintain two different places.

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    public function __invoke(Upcast $upcast): Upcast
    {
        // ignore if other event is processed
        if ($upcast->eventName !== 'profile.created') {
            return $upcast;
        }

        if (!array_key_exists('email', $upcast->payload) || !is_string($upcast->payload['email'])) {
            return $upcast;
        }

        return $upcast->replacePayloadByKey('email', strtolower($upcast->payload['email']));
    }
}
```
!!! warning

    You need to consider that other events are passed to the Upcaster. So and early out is here endorsed.
    
## Adjust event name

Sometimes your event name was not the best choice and you want to change it.
For this we can use the `Upcaster` to change the event name.

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class EventNameRenameUpcaster implements Upcaster
{
    /** @param array<string, string> $eventNameMap */
    public function __construct(
        private readonly array $eventNameMap,
    ) {
    }

    public function __invoke(Upcast $upcast): Upcast
    {
        if (array_key_exists($upcast->eventName, $this->eventNameMap)) {
            return $upcast->replaceEventName($this->eventNameMap[$upcast->eventName]);
        }

        return $upcast;
    }
}
```
!!! tip

    Events can also have [aliases](./events.md#alias). This is usually sufficient.
    
## Configure

After we have defined the upcasting rules, we also have to pass the whole thing to the serializer.
Since we have multiple upcasters, we use a chain here.

```php
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;

/** @var EventRegistry $eventRegistry */
$upcaster = new UpcasterChain([
    new ProfileCreatedEmailLowerCastUpcaster(),
    new EventNameRenameUpcaster(['old_event_name' => 'new_event_name']),
]);

$serializer = DefaultEventSerializer::createFromPaths(
    ['src/Domain'],
    $upcaster,
);
```
## Learn more

* [How to create messages](message.md)
* [How to define events](events.md)
* [How to configure store](store.md)
