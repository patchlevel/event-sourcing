# Message Decorator

There are usecases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. We are
internally using this to save the point of time the event is recorded. All decorators are always executed in a chain.

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
!!! note

    You can also set multiple headers with `withCustomHeaders` which expects an hashmap.

## Adding a decorator

