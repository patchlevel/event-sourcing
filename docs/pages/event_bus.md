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

### Default event bus

The library also delivers a light-weight event bus for which you can register listeners/subscribers and dispatch events.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;

$eventBus = new DefaultEventBus();
$eventBus->addListener($mailListener);
$eventBus->addListener($projectionListener);
```

!!! note

    You can determine the order in which the listeners are executed. For example, 
    you can also add listeners after `ProjectionListener`
    to access the [projections](./projection.md).

### Symfony event bus

You can also use the [symfony message bus](https://symfony.com/doc/current/components/messenger.html)
which is much more powerful.

To use the optional symfony messenger you first have to `install` the packet.

```bash
composer require symfony/messenger
```

You can either let us build it with the `create` factory:

```php
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;

$eventBus = SymfonyEventBus::create([
    $mailListener,
    $projectionListener
]);
```

!!! note

    You can determine the order in which the listeners are executed. For example,
    you can also add listeners after `ProjectionListener`
    to access the [projections](./projection.md).

Or plug it together by hand:

```php
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;

$symfonyMessenger = //...

$eventBus = new SymfonyEventBus($symfonyMessenger);
```

!!! warning

    You can't mix it with a command bus.
    You should create a [new bus](https://symfony.com/doc/current/messenger/multiple_buses.html) for it.

!!! note

    An event bus can have zero or more listeners on an event. 
    You should allow no handler in the [HandleMessageMiddleware](https://symfony.com/doc/current/components/messenger.html).

## Listener

A listener must implement the `Listener` interface and define the `__invoke` method.
This listener is then called for all saved events / messages.

```php
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

$listener = new class implements Listener 
{
    public function __invoke(Message $message): void
    {
        if ($message->event() instanceof ProfileCreated) {
            echo 'Welcome!';
        }
    }
}
```

!!! warning

    If you only want to listen to certain messages, 
    then you have to check it in the `__invoke` method or use the subscriber.

## Subscriber

A `Subscriber` is a listener, except that it has implemented the invoke method itself. 
Instead, you can define your own and multiple methods and listen for specific events with the attribute `Handle`.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

$listener = new class extends Subscriber 
{
    #[Handle(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        echo 'Welcome!';
    }
}
```
