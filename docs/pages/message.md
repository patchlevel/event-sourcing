# Message

A message is a construct that contains additional meta information for each event in the form of headers.
The messages are created in the repository as soon as an aggregate is saved.
These messages are then stored in the store and dispatched to the event bus.

Here is a simple example without headers:

```php
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create(new NameChanged('foo'));
```
!!! note

    You don't have to create the message yourself, it is automatically created, saved and dispatched in
    the [repository](repository.md).
    
You can add a header using `withHeader`:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\Message;

$clock = new SystemClock();
$message = Message::create(new NameChanged('foo'))
    ->withHeader(new AggregateHeader(
        aggregateName: 'profile',
        aggregateId: 'bca7576c-536f-4428-b694-7b1f00c714b7',
        playhead: 2,
        recordedOn: $clock->now(),
    ));
```
!!! note

    The message object is immutable. It creates a new instance with the new data.
    
You can also access the headers:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;

/** @var Message $message */
$message->header(AggregateHeader::class); // AggregateHeader object
$message->hasHeader(AggregateHeader::class); // true
$message->headers(); // [AggregateHeader object]
```
## Built-in headers

The message object has some built-in headers which are used internally.

* `AggregateHeader` - Contains the aggregate name, aggregate id, playhead and recorded on.
* `ArchivedHeader` - Flag if the message is archived.
* `StreamStartHeader` - Flag if the message is the first message in a new stream.

## Custom headers

You can also add custom headers to the message object. For example, you can add an application id.
To do this, you need to create a Header class.

```php
use Patchlevel\EventSourcing\Attribute\Header;

#[Header('application')]
class ApplicationHeader
{
    public function __construct(
        private readonly string $id,
    ) {
    }
}
```
Then you can add the header to the message object.

```php
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create(new NameChanged('foo'))
    ->withHeader(new ApplicationHeader('app'));
```
!!! warning

    The header needs to be serializable. The library uses the hydrator to serialize and deserialize the headers.
    So you can add normalize attributes to the properties if needed.
    
!!! note

    You can read about how to pass additional headers to the message object in the [message decorator](message_decorator.md) docs.
    
You can also access your custom headers:

```php
use Patchlevel\EventSourcing\Message\Message;

/** @var Message $message */
$message->header(ApplicationHeader::class);
```
## Translator

Translator can be used to manipulate, filter or expand messages or events.
This can be used for anti-corruption layers, data migration, or to fix errors in the event stream.

### Exclude

With this translator you can exclude certain events.

```php
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;

$translator = new ExcludeEventTranslator([EmailChanged::class]);
```
### Include

With this translator you can only allow certain events.

```php
use Patchlevel\EventSourcing\Message\Translator\IncludeEventTranslator;

$translator = new IncludeEventTranslator([ProfileCreated::class]);
```
### Filter

If the translator `ExcludeEventTranslator` and `IncludeEventTranslator` are not sufficient,
you can also write your own filter.
This translator expects a callback that returns either true to allow events or false to not allow them.

```php
use Patchlevel\EventSourcing\Message\Translator\FilterEventTranslator;

$translator = new FilterEventTranslator(static function (object $event) {
    if (!$event instanceof ProfileCreated) {
        return true;
    }

    return $event->allowNewsletter();
});
```
### Exclude Events with Header

With this translator you can exclude event with specific header.

```php
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventWithHeaderTranslator;
use Patchlevel\EventSourcing\Store\ArchivedHeader;

$translator = new ExcludeEventWithHeaderTranslator(ArchivedHeader::class);
```
### Only Events with Header

With this translator you can only allow events with a specific header.

```php
use Patchlevel\EventSourcing\Message\Translator\IncludeEventWithHeaderTranslator;

$translator = new IncludeEventWithHeaderTranslator(ArchivedHeader::class);
```
### Replace

If you want to replace an event, you can use the `ReplaceEventTranslator`.
The first parameter you have to define is the event class that you want to replace.
And as a second parameter a callback, that the old event awaits and a new event returns.

```php
use Patchlevel\EventSourcing\Message\Translator\ReplaceEventTranslator;

$translator = new ReplaceEventTranslator(OldVisited::class, static function (OldVisited $oldVisited) {
    return new NewVisited($oldVisited->profileId());
});
```
### Until

A use case could also be that you want to look at the projection from a previous point in time.
You can use the `UntilEventTranslator` to only allow events that were `recorded` before this point in time.

```php
use Patchlevel\EventSourcing\Message\Translator\UntilEventTranslator;

$translator = new UntilEventTranslator(new DateTimeImmutable('2020-01-01 12:00:00'));
```
### Recalculate playhead

This translator can be used to recalculate the playhead.
The playhead must always be in ascending order so that the data is valid.
Some translator can break this order and the translator `RecalculatePlayheadTranslator` can fix this problem.

```php
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;

$translator = new RecalculatePlayheadTranslator();
```
!!! tip

    If you migrate your event stream, you can use the `RecalculatePlayheadTranslator` to fix the playhead.
    
### Chain

If you want to group your translator, you can use one or more `ChainTranslator`.

```php
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;

$translator = new ChainTranslator([
    new ExcludeEventTranslator([EmailChanged::class]),
    new RecalculatePlayheadTranslator(),
]);
```
### Custom Translator

You can also write a custom translator. The translator gets a message and can return `n` messages.
There are the following possibilities:

* Return only the message to an array to leave it unchanged.
* Put another message in the array to swap the message.
* Return an empty array to remove the message.
* Or return multiple messages to enrich the stream.

In our case, the domain has changed a bit.
In the beginning we had a `ProfileCreated` event that just created a profile.
Now we have a `ProfileRegistered` and a `ProfileActivated` event,
which should replace the `ProfileCreated` event.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\Translator;

final class SplitProfileCreatedTranslator implements Translator
{
    public function __invoke(Message $message): array
    {
        $event = $message->event();

        if (!$event instanceof ProfileCreated) {
            return [$message];
        }

        $profileRegisteredMessage = Message::createWithHeaders(
            new ProfileRegistered($event->id(), $event->name()),
            $message->headers(),
        );

        $profileActivatedMessage = Message::createWithHeaders(
            new ProfileActivated($event->id()),
            $message->headers(),
        );

        return [$profileRegisteredMessage, $profileActivatedMessage];
    }
}
```
!!! warning

    Since we changed the number of messages, we have to recalculate the playhead.
    
!!! tip

    You don't have to migrate the store directly for every change, 
    but you can also use the [upcasting](upcasting.md) feature.
    
## Learn more

* [How to decorate messages](message_decorator.md)
* [How to load aggregates](repository.md)
* [How to store messages](store.md)
* [How to use subscriptions](subscription.md)
* [How to use the event bus](event_bus.md)
