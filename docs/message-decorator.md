# Message Decorator

There are use-cases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. The metadata
will also be persisted in the database and can be retrieved later on.

## Built-in decorator

We offer a few decorators that you can use.

### SplitStreamDecorator

In order to use the [split stream](split-stream.md) feature, the `SplitStreamDecorator` must be added.

```php
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;

$eventMetadataFactory = new AttributeEventMetadataFactory();
$decorator = new SplitStreamDecorator($eventMetadataFactory);
```
### ChainMessageDecorator

To use multiple decorators at the same time, you can use the `ChainMessageDecorator`.

```php
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;

/**
 * @var MessageDecorator $decorator1
 * @var MessageDecorator $decorator2
 */
$decorator = new ChainMessageDecorator([
    $decorator1,
    $decorator2,
]);
```
## Use decorator

To use the message decorator, you have to pass it to the `DefaultRepositoryManager`,
which will then pass it to all Repositories.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Store\Store;

/** @var EventMetadataFactory $eventMetadataFactory */
$decorator = new ChainMessageDecorator([new SplitStreamDecorator($eventMetadataFactory)]);

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    null,
    null,
    $decorator,
);

$repository = $repositoryManager->get(Profile::class);
```
:::note
You can find out more about the [repository](repository.md).
:::

## Create own decorator

You can also use this feature to add your own metadata to your events. For this, the `Message` has extra methods:
`withHeader` to add data and `header` to read this data later on.

```php
use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;

#[Header('system')]
final class SystemHeader
{
    public function __construct(
        public string $system,
    ) {
    }
}

final class OnSystemRecordedDecorator implements MessageDecorator
{
    public function __invoke(Message $message): Message
    {
        return $message->withHeader(new SystemHeader('system'));
    }
}
```
:::note
The message is immutable, more information can be found in the [message](message.md) documentation.
:::

:::tip
You can also set multiple headers with `withHeaders` which expects a list of headers.
:::

## Learn more

* [How to create messages](message.md)
* [How to define events](events.md)
* [How to configure repositories](repository.md)
* [How to upcast events](upcasting.md)
