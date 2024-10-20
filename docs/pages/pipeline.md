# Pipeline / Anti Corruption Layer

As soon as you have the case where you want to flow a lot of events from A to B,
and maybe even influence the events in the form of filters or manipulations,
then the pipeline comes into play.

There are several situations in which a pipeline makes sense:

* Migration of the event store and its events.
* As an anti-corruption layer when publishing events to other systems.
* Or as an anti-corruption layer when importing events from other systems.

## Pipe

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile

$pipe = new Pipe(
    $oldStore->load(),
    new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
        return new NewVisited($oldVisited->profileId());
    }),
);

$unwarp = function (iterable $messages) {
    foreach ($messages as $message) {
        yield $message->event();
    }
};

Profile::createFromEvents($unwarp($pipe));
```

## Pipeline

The pipeline uses the pipe internally and is an abstraction layer on top of it.
The pipeline is used when it comes to moving a lot of events from A to B.
A `target` must be defined where the events should flow to.
You can also define whether buffering should take place or not.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$pipeline = new Pipeline(
    new StoreTarget($newStore),
    new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
        return new NewVisited($oldVisited->profileId());
    }),
);

$pipeline->run($oldStore->load());
```

!!! danger

    Under no circumstances should the same store be used as target as the one used as source. 
    Otherwise the store will be broken afterwards!

## Target



### Store

You can use a store to save the final result.

```php
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$target = new StoreTarget($store);
```

!!! danger

    Under no circumstances may the same store be used that is used for the source. 
    Otherwise the store will be broken afterwards!

!!! note

    It does not matter whether the previous store was a SingleTable or a MultiTable.
    You can switch back and forth between both store types using the pipeline.

### Event Bus

A projection can also be used as a target.
For example, to set up a new projection or to build a new projection.

```php
use Patchlevel\EventSourcing\Pipeline\Target\EventBusTarget;

$target = new EventBusTarget($projection);
```

### In Memory

There is also an in-memory variant for the target. This target can also be used for tests.
With the `messages` method you get all `Messages` that have reached the target.

```php
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;

$target = new InMemoryTarget();

// run pipeline

$messages = $target->messages();
```

### Custom Target

You can also define your own target. To do this, you need to implement the `Target` interface.

```php
use Patchlevel\EventSourcing\EventBus\Message;

final class OtherStoreTarget implements Target
{
    private OtherStore $store;

    public function __construct(OtherStore $store)
    {
        $this->store = $store;
    }

    public function save(Message $message): void
    {
        $this->store->save($message);
    }
}
```

## Middlewares

Middelwares can be used to manipulate, delete or expand messages or events during the process.

!!! warning

    It is important to know that some middlewares require recalculation from the playhead,
    if the target is a store. This is a numbering of the events that must be in ascending order.
    A corresponding note is supplied with every middleware.

### Exclude

With this middleware you can exclude certain events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;

$middleware = new ExcludeEventMiddleware([EmailChanged::class]);
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Include


With this middleware you can only allow certain events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware;

$middleware = new IncludeEventMiddleware([ProfileCreated::class]);
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Filter

If the middlewares `ExcludeEventMiddleware` and `IncludeEventMiddleware` are not sufficient,
you can also write your own filter.
This middleware expects a callback that returns either true to allow events or false to not allow them.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\Middleware\FilterEventMiddleware;

$middleware = new FilterEventMiddleware(function (AggregateChanged $event) {
    if (!$event instanceof ProfileCreated) {
        return true;
    }
    
    return $event->allowNewsletter();
});
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Exclude Archived Events

With this middleware you can exclude archived events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeArchivedEventMiddleware;

$middleware = new ExcludeArchivedEventMiddleware();
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Only Archived Events


With this middleware you can only allow archived events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\OnlyArchivedEventMiddleware;

$middleware = new OnlyArchivedEventMiddleware();
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Replace

If you want to replace an event, you can use the `ReplaceEventMiddleware`.
The first parameter you have to define is the event class that you want to replace.
And as a second parameter a callback, that the old event awaits and a new event returns.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;

$middleware = new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
    return new NewVisited($oldVisited->profileId());
});
```

!!! note

    The middleware takes over the playhead and recordedAt information.

### Until

A use case could also be that you want to look at the projection from a previous point in time.
You can use the `UntilEventMiddleware` to only allow events that were `recorded` before this point in time.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware;

$middleware = new UntilEventMiddleware(new DateTimeImmutable('2020-01-01 12:00:00'));
```

!!! warning

    After this middleware, the playhead must be recalculated!

### Recalculate playhead

This middleware can be used to recalculate the playhead.
The playhead must always be in ascending order so that the data is valid.
Some middleware can break this order and the middleware `RecalculatePlayheadMiddleware` can fix this problem.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;

$middleware = new RecalculatePlayheadMiddleware();
```

!!! note

    You only need to add this middleware once at the end of the pipeline.

### Chain

If you want to group your middleware, you can use one or more `ChainMiddleware`.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;

$middleware = new ChainMiddleware([
    new ExcludeEventMiddleware([EmailChanged::class]),
    new RecalculatePlayheadMiddleware()
]);
```

### Custom middleware

You can also write a custom middleware. The middleware gets a message and can return `N` messages.
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
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;

final class SplitProfileCreatedMiddleware implements Middleware
{
    public function __invoke(Message $message): array
    {
        $event = $message->event();
        
        if (!$event instanceof ProfileCreated) {
            return [$message];
        }
        
        $profileRegisteredMessage = Message::createWithHeaders(
            new ProfileRegistered($event->id(), $event->name()), 
            $message->headers()
        );
        
        $profileActivatedMessage = Message::createWithHeaders(
            new ProfileActivated($event->id()), 
            $message->headers()
        );

        return [$profileRegisteredMessage, $profileActivatedMessage];
    }    
}
```

!!! warning

    Since we changed the number of messages, we have to recalculate the playhead.

!!! note

    You can find more about messages [here](event_bus.md).