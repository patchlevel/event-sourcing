# Pipeline

A store is immutable, i.e. it cannot be changed afterwards.
This includes both manipulating events and deleting them.

Instead, you can duplicate the store and manipulate the events in the process.
Thus the old store remains untouched and you can test the new store beforehand,
whether the migration worked.

In this example the event `PrivacyAdded` is removed and the event `OldVisited` is replaced by `NewVisited`:

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$pipeline = new Pipeline(
    new StoreSource($oldStore),
    new StoreTarget($newStore),
    [
        new ExcludeEventMiddleware([PrivacyAdded::class]),
        new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
            return NewVisited::raise($oldVisited->profileId());
        }),
        new RecalculatePlayheadMiddleware(),
    ]
);
```

> :warning: Under no circumstances may the same store be used that is used for the source.
> Otherwise the store will be broken afterwards!

The pipeline can also be used to create or rebuild a projection:

```php
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;

$pipeline = new Pipeline(
    new StoreSource($store),
    new ProjectionTarget($projectionHandler, [ProfileProjection::class])
);
```

The principle remains the same. 
There is a source where the data comes from.
A target where the data should flow.
And any number of middlewares to do something with the data beforehand.

## EventBucket

The pipeline works with so-called `EventBucket`. 
This `EventBucket` wraps the event or `AggregateChanged` and adds further meta information
like the `aggregateClass` and the event `index`.

## Source

The first thing you need is a source of where the data should come from.

### Store

The `StoreSource` is the standard source to load all events from the database.

```php
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;

$source = new StoreSource($store);
```

### In Memory

There is an `InMemorySource` that receives the events in an array. This source can be used to write pipeline tests.

```php
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;

$source = new InMemorySource([
    new EventBucket(
        Profile::class,
        ProfileCreated::raise(Email::fromString('david.badura@patchlevel.de'))->recordNow(0),
    ),
    // ...
]);
```

### Custom Source

You can also create your own source class. It has to inherit from `Source`. 
Here you can, for example, create a migration from another event sourcing system or similar system.

```php
use Patchlevel\EventSourcing\Pipeline\Source\Source;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

$source = new class implements Source {
    /**
     * @return Generator<EventBucket>
     */
    public function load(): Generator
    {
        yield new EventBucket(Profile::class, 0, new ProfileCreated('1', ['name' => 'David']));
    }

    public function count(): int
    {
        reutrn 1;
    }
}
```

## Target

After you have a source, you still need the destination of the pipeline.

### Store

You can use a store to save the final result.

```php
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$target = new StoreTarget($store);
```
> :warning: Under no circumstances may the same store be used that is used for the source. 
> Otherwise the store will be broken afterwards!

> :book: It does not matter whether the previous store was a SingleTable or a MultiTable.
> You can switch back and forth between both store types using the pipeline.

### Projection

If you want to build or create all projections from scratch,
then you can also use the ProjectionHandlerTarget. 
In this, the individual projections are iterated and the events are then passed on.

```php
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;

$target = new ProjectionTarget($projectionHandler);
```

You can also specify only certain projections by passing the respective classes as the second parameter.

```php
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;

$target = new ProjectionTarget($projectionHandler, [ProfileProjection::class]);
```

### In Memory

There is also an in-memory variant for the target. This target can also be used for tests.
With the `buckets` method you get all `EventBuckets` that have reached the target.

```php
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;

$target = new InMemoryTarget();

// run pipeline

$buckets = $target->buckets();
```

## Middlewares

Middelwares can be used to manipulate, delete or expand events during the process.

> :warning: It is important to know that some middlewares require recalculation from the playhead,
> if the target is a store.
> This is a numbering of the events that must be in ascending order.
> A corresponding note is supplied with every middleware.

### exclude

With this middleware you can exclude certain events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;

$middleware = new ExcludeEventMiddleware([EmailChanged::class]);
```

> :warning: After this middleware, the playhead must be recalculated!

### include


With this middleware you can only allow certain events.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware;

$middleware = new IncludeEventMiddleware([ProfileCreated::class]);
```

> :warning: After this middleware, the playhead must be recalculated!

### filter

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

> :warning: After this middleware, the playhead must be recalculated!

### replace

If you want to replace an event, you can use the `ReplaceEventMiddleware`.
The first parameter you have to define is the event class that you want to replace.
And as a second parameter a callback, that the old event awaits and a new event returns.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;

$middleware = new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
    return NewVisited::raise($oldVisited->profileId());
});
```

> :book: The middleware takes over the playhead and recordedAt information.

### class rename

When mapping is not necessary and you just want to rename the class
(e.g. if namespaces have changed), then you can use the `ClassRenameMiddleware`.
You have to pass a hash map. The key is the old class name and the value is the new class name.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware;

$middleware = new ClassRenameMiddleware([
    OldVisited::class => NewVisited::class
]);
```

> :book: The middleware takes over the payload, playhead and recordedAt information.

### until

A use case could also be that you want to look at the projection from a previous point in time.
You can use the `UntilEventMiddleware` to only allow events that were `recorded` before this point in time.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware;

$middleware = new UntilEventMiddleware(new DateTimeImmutable('2020-01-01 12:00:00'));
```

> :warning: After this middleware, the playhead must be recalculated!

### recalculate playhead

This middleware can be used to recalculate the playhead.
The playhead must always be in ascending order so that the data is valid. 
Some middleware can break this order and the middleware `RecalculatePlayheadMiddleware` can fix this problem.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;

$middleware = new RecalculatePlayheadMiddleware();
```

> :book: You only need to add this middleware once at the end of the pipeline.

### chain

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
