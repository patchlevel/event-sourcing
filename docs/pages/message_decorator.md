# Message Decorator

There are usecases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. The metadata
will also be persisted in the database and can be retrieved later on. 

## Built-in decorator

We offer a few decorators that you can use.

### SplitStreamDecorator

In order to use the [split stream](split_stream.md) feature, the `SplitStreamDecorator` must be added.

```php
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;

$eventMetadataFactory = new AttributeEventMetadataFactory();
$decorator = new SplitStreamDecorator($eventMetadataFactory);
```

### ChainMessageDecorator

To use multiple decorators at the same time, one can use the `ChainMessageDecorator`.

```php
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;

$decorator = new ChainMessageDecorator([
    $decorator1,
    $decorator2,
]);
```

## Use decorator

To use the message decorator, you have to pass it to the `DefaultRepositoryManager`.

```php
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;

$decorator = new ChainMessageDecorator([
    new SplitStreamDecorator($eventMetadataFactory)  
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

!!! note

    You can find out more about repository [here](repository).

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
