# Event Bus

This library uses the core principle called [event bus](https://martinfowler.com/articles/201701-event-driven.html).

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event wrapped in a message will be dispatched to the `event bus`. All listeners are then called for each
event/message.

## Message

A `Message` contains the event and related meta information such as the aggregate class and id.
A message contains the following information:

* aggregate class
* aggregate id
* playhead
* event
* recorded on
* custom headers

Each event is packed into a message and dispatched using the event bus.

```php
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Message;

$clock = SystemClock();
$message = Message::create(new NameChanged('foo'))
    ->withAggregateClass(Profile::class)
    ->withAggregateId('bca7576c-536f-4428-b694-7b1f00c714b7')
    ->withPlayhead(2)
    ->withRecordedOn($clock->now());

$eventBus->dispatch($message);
```

!!! note

    The message object is immutable.

You don't have to create the message yourself,
it is automatically created, saved and dispatched in the [repository](repository.md).

### Custom headers

You can also enrich your own header or metadata information.
This information is then accessible in the message object and is also stored in the database.

```php
use Patchlevel\EventSourcing\EventBus\Message;

$message = Message::create(new NameChanged('foo'))
    // ...
    ->withCustomHeader('application-id', 'app');
```

!!! note

    You can read about how to pass additional headers to the message object in the [message decorator](message_decorator.md) docs.

You can also access your custom headers.

```php
use Patchlevel\EventSourcing\EventBus\Message;

$message->customHeader('application-id'); // app
$message->customHeaders(); // ['application-id' => 'app']
```

## Event Bus

The event bus is responsible for dispatching the messages to the listeners.
The library also delivers a light-weight event bus for which you can register listeners and dispatch events.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;

$eventBus = DefaultEventBus::create([
    $mailListener,
]);
```

!!! note

    The order in which the listeners are executed is determined by the order in which they are passed to the factory.

Internally, the event bus uses the `Consumer` to consume the messages and call the listeners.

## Consumer

The consumer is responsible for consuming the messages and calling the listeners.

```php
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;

$consumer = DefaultConsumer::create([
    $mailListener,
];

$consumer->consume($message);
```

Internally, the consumer uses the `ListenerProvider` to find the listeners for the message.

## Listener provider

The listener provider is responsible for finding all listeners for a specific event.
The default listener provider uses attributes to find the listeners.

```php
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;

$listenerProvider = new AttributeListenerProvider([
    $mailListener,
]);

$eventBus = new DefaultEventBus(
    new DefaultConsumer($listenerProvider)
);
```

!!! tip

    The `DefaultEventBus::create` method uses the `DefaultConsumer` and `AttributeListenerProvider` by default.

### Custom listener provider

You can also use your own listener provider.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;

$listenerProvider = new class implements ListenerProvider {
    public function listenersForEvent(string $eventClass): iterable
    {
        return [
            new ListenerDescriptor(
                (new WelcomeSubscriber())->onProfileCreated(...),
            ),
        ];
    }
};
```

!!! tip

    You can use `$listenerDiscriptor->name()` to get the name of the listener.

## Listener

You can listen for specific events with the attribute `Subscribe`.
This listener is then called for all saved events / messages.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class WelcomeSubscriber 
{
    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        echo 'Welcome!';
    }
}
```

!!! tip

    If you use psalm, you can use the [event sourcing plugin](https://github.com/patchlevel/event-sourcing-psalm-plugin) for better type support.

### Listen on all events

If you want to listen on all events, you can pass `*` or `Subscribe::ALL` instead of the event class.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class WelcomeSubscriber 
{
    #[Subscribe('*')]
    public function onProfileCreated(Message $message): void
    {
        echo 'Welcome!';
    }
}
```

## Psr-14 Event Bus

You can also use a [psr-14](https://www.php-fig.org/psr/psr-14/) compatible event bus.
In this case, you can't use the `Subscribe` attribute. 
You need to use the system of the psr-14 event bus.

```php
use Patchlevel\EventSourcing\EventBus\Psr14EventBus;

$eventBus = new Psr14EventBus($psr14EventDispatcher);
```

!!! warning

    You can't use the `Subscribe` attribute with the psr-14 event bus.

## Learn more

* [How to decorate messages](message_decorator.md)
* [How to use outbox pattern](outbox.md)
* [How to use processor](processor.md)
* [How to use projections](projection.md)
* [How to debug messages with the watch server](watch_server.md)
