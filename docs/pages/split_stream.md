# Split Stream

In some cases the business has rules which implies a restart of the event stream for an aggregate
since the past events are not relevant for the current state.
A bank is often used as an example. A bank account has hundreds of transactions,
but every bank makes a balance report at the end of the year.
In this step the current account balance is persisted.
This event is perfect to split the stream and start aggregating from this point.

Not only that some businesses requires such an action
it also increases the performance for aggregate which would have a really long event stream.

In the background the library will mark all past events as archived
and will not load them anymore for building the aggregate.
It will only load the events from the split event and onwards.
But subscriptions will still receive all events.
So you can create projections which are based on the full event stream.

## Configuration

To use this feature you need to add the `SplitStreamDecorator` in the repository manager.

```php
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 * @var EventMetadataFactory $eventMetadataFactory
 */
$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    null,
    null,
    new SplitStreamDecorator($eventMetadataFactory),
);
```
!!! note

    You can find out more about decorator [here](./message_decorator.md).
    
!!! tip

    You can use multiple decorators with the `ChainMessageDecorator`.
    
## Usage

To use this feature you need to mark the event which should split the stream.
For that you can use the `#[SplitStream]` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('bank_account.balance_reported')]
#[SplitStream]
final class BalanceReported
{
    public function __construct(
        #[IdNormalizer]
        public BankAccountId $bankAccountId,
        public int $year,
        public int $balanceInCents,
    ) {
    }
}
```
!!! warning

    The event needs all data which is relevant the aggregate to be used since all past event will not be loaded! 
    Keep this in mind if you want to use this feature.
    
!!! note

    This impacts only the aggregate loaded by the repository. Subscriptions will still receive all events.
    
!!! tip

    You can combine this feature with the snapshot feature to increase the performance even more.