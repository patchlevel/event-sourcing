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

## Learn more

* [How to decorate messages](message_decorator.md)
* [How to load aggregates](repository.md)
* [How to store messages](store.md)
* [How to use subscriptions](subscription.md)
* [How to use the event bus](event_bus.md)
* [How to migrate messages](pipeline.md)
