# Message Decorator

There are usecases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. The metadata
will also be persisted in the database and can be retrieved later on. 

## Create own decorator

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

    The Message is immutable, for more information look up [here](event_bus.md#message).

!!! tip

    You can also set multiple headers with `withCustomHeaders` which expects an hashmap.

## Use own decorator

To use your own message decorator, you have to pass it to the `DefaultRepositoryManager`.

```php
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;

$decorator = new ChainMessageDecorator([
    new RecordedOnDecorator($clock),
    new OnSystemRecordedDecorator()  
]);

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $eventBus,
    null,
    $decorator
);

$repository = $repositoryManager->get(Profile::class);
```

!!! warning

    We also use the decorator to fill in the `recordedOn` time. 
    If you want to add your own decorator, then you need to make sure to add the `RecordedOnDecorator` as well. 
    You can e.g. solve with the `ChainMessageDecorator`.

!!! note

    You can find out more about repository [here](repository).