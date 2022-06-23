# Message Decorator

There are usecases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. The metadata
will also be persisted in the database and can be retrieved later on. We are internally using this to save the point of
time the event is recorded. Here is the code from this message decorator.

```php
use Patchlevel\EventSourcing\Clock\Clock;
use Patchlevel\EventSourcing\EventBus\Message;

final class RecordedOnDecorator implements MessageDecorator
{
    public function __construct(private readonly Clock $clock)
    {
    }

    public function __invoke(Message $message): Message
    {
        return $message->withRecordedOn($this->clock->now());
    }
} 
```

!!! note

    The Message is immutable, for more information look up [here](event_bus.md#message).

You can also use this feature to add your own metadata to your events. For this the have an extra methods on `Message`
to add data `withCustomHeader` and to read this data later on `customHeader`.

```php
use Patchlevel\EventSourcing\EventBus\Message;

final class OnSystemRecordedDecorator implements MessageDecorator
{
    public function __invoke(Message $message): Message
    {
        return $message->withCustomHeader('system', 'accounting_system');
    }
} 
```

!!! tip

    You can also set multiple headers with `withCustomHeaders` which expects an hashmap.

## Adding a message decorator

