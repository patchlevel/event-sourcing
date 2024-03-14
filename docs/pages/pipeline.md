# Pipeline

A store is immutable, i.e. it cannot be changed afterwards.
This includes both manipulating events and deleting them.

Instead, you can duplicate the store and manipulate the events in the process.
Thus the old store remains untouched and you can test the new store beforehand,
whether the migration worked.

In this example the event `PrivacyAdded` is removed and the event `OldVisited` is replaced by `NewVisited`:

```php
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Message\Translator\ReplaceEventTranslator;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$pipeline = new Pipeline(
    new StoreSource($oldStore),
    new StoreTarget($newStore),
    [
        new ExcludeEventTranslator([PrivacyAdded::class]),
        new ReplaceEventTranslator(OldVisited::class, static function (OldVisited $oldVisited) {
            return new NewVisited($oldVisited->profileId());
        }),
        new RecalculatePlayheadTranslator(),
    ],
);
```
!!! danger

    Under no circumstances may the same store be used that is used for the source.
    Otherwise the store will be broken afterwards!
    
The pipeline can also be used to create or rebuild a projection:

```php
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ConsumerTarget;

$pipeline = new Pipeline(
    new StoreSource($store),
    ConsumerTarget::create([$projection]),
);
```
The principle remains the same.
There is a source where the data comes from.
A target where the data should flow.
And any number of middlewares to do something with the data beforehand.

## Source

The first thing you need is a source of where the data should come from.

### Store

The `StoreSource` is the standard source to load all events from the database.

```php
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;

$source = new StoreSource($store);
```
### In Memory

There is an `InMemorySource` that receives the messages in an array. This source can be used to write pipeline tests.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;

$source = new InMemorySource([
    new Message(
        Profile::class,
        '1',
        1,
        new ProfileCreated(Email::fromString('david.badura@patchlevel.de')),
    ),
    // ...
]);
```
### Custom Source

You can also create your own source class. It has to inherit from `Source`.
Here you can, for example, create a migration from another event sourcing system or similar system.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Source\Source;

$source = new class implements Source {
    /** @return Generator<Message> */
    public function load(): Generator
    {
        yield new Message(
            Profile::class,
            '1',
            0,
            new ProfileCreated('1', ['name' => 'David']),
        );
    }

    public function count(): int
    {
        return 1;
    }
};
```
## Target

After you have a source, you still need the destination of the pipeline.

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
    
### Consumer

A consumer can also be used as a target.

```php
use Patchlevel\EventSourcing\Pipeline\Target\ConsumerTarget;

$target = new ConsumerTarget($consumer);
```
!!! tip

    You can also use it to build a new projection from scratch.
    
!!! note

    More about the consumer can be found [here](event_bus.md).
    
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
use Patchlevel\EventSourcing\Message\Message;

final class OtherStoreTarget implements Target
{
    public function __construct(private OtherStore $store)
    {
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
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;

$middleware = new ExcludeEventTranslator([EmailChanged::class]);
```
!!! warning

    After this middleware, the playhead must be recalculated!
    
### Include

With this middleware you can only allow certain events.

```php
use Patchlevel\EventSourcing\Message\Translator\IncludeEventTranslator;

$middleware = new IncludeEventTranslator([ProfileCreated::class]);
```
!!! warning

    After this middleware, the playhead must be recalculated!
    
### Filter

If the middlewares `ExcludeEventMiddleware` and `IncludeEventMiddleware` are not sufficient,
you can also write your own filter.
This middleware expects a callback that returns either true to allow events or false to not allow them.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Message\Translator\FilterEventTranslator;

$middleware = new FilterEventTranslator(static function (AggregateChanged $event) {
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
use Patchlevel\EventSourcing\Message\Translator\ExcludeArchivedEventTranslator;

$middleware = new ExcludeArchivedEventTranslator();
```
!!! warning

    After this middleware, the playhead must be recalculated!
    
### Only Archived Events

With this middleware you can only allow archived events.

```php
use Patchlevel\EventSourcing\Message\Translator\OnlyArchivedEventTranslator;

$middleware = new OnlyArchivedEventTranslator();
```
!!! warning

    After this middleware, the playhead must be recalculated!
    
### Replace

If you want to replace an event, you can use the `ReplaceEventMiddleware`.
The first parameter you have to define is the event class that you want to replace.
And as a second parameter a callback, that the old event awaits and a new event returns.

```php
use Patchlevel\EventSourcing\Message\Translator\ReplaceEventTranslator;

$middleware = new ReplaceEventTranslator(OldVisited::class, static function (OldVisited $oldVisited) {
    return new NewVisited($oldVisited->profileId());
});
```
!!! note

    The middleware takes over the playhead and recordedAt information.
    
### Until

A use case could also be that you want to look at the projection from a previous point in time.
You can use the `UntilEventMiddleware` to only allow events that were `recorded` before this point in time.

```php
$middleware = new UntilEventMiddleware(new DateTimeImmutable('2020-01-01 12:00:00'));
```
!!! warning

    After this middleware, the playhead must be recalculated!
    
### Recalculate playhead

This middleware can be used to recalculate the playhead.
The playhead must always be in ascending order so that the data is valid.
Some middleware can break this order and the middleware `RecalculatePlayheadMiddleware` can fix this problem.

```php
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;

$middleware = new RecalculatePlayheadTranslator();
```
!!! note

    You only need to add this middleware once at the end of the pipeline.
    
### Chain

If you want to group your middleware, you can use one or more `ChainMiddleware`.

```php
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;

$middleware = new ChainTranslator([
    new ExcludeEventTranslator([EmailChanged::class]),
    new RecalculatePlayheadTranslator(),
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
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\Translator;

final class SplitProfileCreatedMiddleware implements Translator
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
    
!!! note

    You can find more about messages [here](event_bus.md).
    