# Message

A `Message` contains the event and related meta information as headers.

```php
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create(new NameChanged('foo'));
```



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

!!! note

    You don't have to create the message yourself, it is automatically created, saved and dispatched in
    the [repository](repository.md).

## Built-in headers

The message object has some built-in headers which are used internally.

* `AggregateHeader` - Contains the aggregate name, aggregate id, playhead and recorded on.
* `ArchivedHeader` - Flag if the message is archived.
* `NewStreamStartHeader` - Flag if the message is the first message in a new stream.

## Custom headers

As already mentioned, you can enrich the `Message` with your own meta information. This is then accessible in the
message object and is also stored in the database.

```php
use Patchlevel\EventSourcing\Message\Message;

$message = Message::create(new NameChanged('foo'))
    // ...
    ->withHeader('application-id', 'app');
```
!!! note

    You can read about how to pass additional headers to the message object in the [message decorator](message_decorator.md) docs.
    
You can also access your custom headers. For this case there is also a method to only retrieve the headers which are not
used internally.

```php
$message->header('application-id'); // app
$message->customHeaders(); // ['application-id' => 'app']
```
If you want *all* the headers you can also retrieve them.

```php
$headers = $message->headers();
/*
[
    'aggregateName' => 'profile',
    'aggregateId' => '1',
    // {...},
    'application-id' => 'app'
]
*/
```
!!! warning

    Relying on internal meta data could be dangerous as they could be changed. So be cautios if you want to implement logic on them.

## Learn more

* [How to decorate messages](message_decorator.md)
* [How to use outbox pattern](outbox.md)
* [How to use processor](subscription.md)
* [How to use subscriptions](subscription.md)
