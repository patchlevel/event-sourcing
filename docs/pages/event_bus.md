# Event Bus

Optionally you can use an event bus to dispatch events to listeners.

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event wrapped in a message will be dispatched to the `event bus`.
All listeners are then called for each message.

!!! tip

    It is recommended to use the [subscription engine](subscription.md) to process the messages.
    It is more powerful and flexible than the event bus.
    
## Event Bus

The library delivers a light-weight event bus for which you can register listeners and dispatch events.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;

$eventBus = DefaultEventBus::create([$mailListener]);
```
!!! note

    The order in which the listeners are executed is determined by the order in which they are passed to the factory.
    
Internally, the event bus uses the `Consumer` to consume the messages and call the listeners.

## Consumer

The consumer is responsible for consuming the messages and calling the listeners.

```php
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;

$consumer = DefaultConsumer::create([$mailListener]);

$consumer->consume($message);
```
Internally, the consumer uses the `ListenerProvider` to find the listeners for the message.

## Listener provider

The listener provider is responsible for finding all listeners for a specific event.
The default listener provider uses attributes to find the listeners.

```php
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;

$listenerProvider = new AttributeListenerProvider([$mailListener]);

$eventBus = new DefaultEventBus(
    new DefaultConsumer($listenerProvider),
);
```
!!! tip

    The `DefaultEventBus::create` method uses the `DefaultConsumer` and `AttributeListenerProvider` by default.
    
### Custom listener provider

You can also use your own listener provider.

```php
use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;

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
use Patchlevel\EventSourcing\Message\Message;

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
use Patchlevel\EventSourcing\Message\Message;

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

* [How to use messages](message.md)
* [How to use events](events.md)
* [How to use the subscription engine](subscription.md)
* [How to use repositories](repository.md)
* [How to use decorate messages](message_decorator.md)
