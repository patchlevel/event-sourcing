# Event Bus

This library uses the core principle called [event bus](https://martinfowler.com/articles/201701-event-driven.html).

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event will be dispatched to the `event bus`. All listeners are then called for each event.

## Default event bus

The library also delivers a light-weight event bus. This can only register listener and dispatch events.

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;

$eventBus = new DefaultEventBus();
$eventBus->addListener($mailListener);
$eventBus->addListener($projectionListener);
```

> :book: If you need more features, use the symfony event bus.

## Symfony event bus

You can also use the [symfony message bus](https://symfony.com/doc/current/components/messenger.html) 
which is much more powerful. You can either let us build it with the `create` factory:

```php
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;

$eventBus = SymfonyEventBus::create([
    $mailListener,
    $projectionListener
]);
```

Or plug it together by hand:

```php
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;

$symfonyMessenger = //...

$eventBus = new SymfonyEventBus($symfonyMessenger);
```

> :warning: Don't forget to allow no handler!

> :warning: You can't mix it with a command bus! Create a new bus for it!

## Listener

A listener must implement the `Listener` interface and define the `__invoke` method.
This listener is then called for all saved events.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

$listener = new class implements Listener 
{
    public function __invoke(AggregateChanged $event): void
    {
        if ($event instanceof ProfileCreated) {
            echo 'Welcome!';
        }
    }
}
```

> :warning: If you only want to listen to certain events, then you have to check it in the `__invoke` method.

> :book: Basically, listeners can be categorized according to their tasks. 
> We have a [processor](./processor.md) and [projections](./projection.md).