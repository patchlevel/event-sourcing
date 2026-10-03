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
:::note
You can find out more about the [message decorator](message-decorator.md).
:::

:::tip
You can use multiple decorators with the `ChainMessageDecorator`.
:::

## Usage

To use this feature you need to mark the event which should split the stream.
For that you can use the `#[SplitStream]` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;

#[Event('bank_account.balance_reported')]
#[SplitStream]
final class BalanceReported
{
    public function __construct(
        public BankAccountId $bankAccountId,
        public int $year,
        public int $balanceInCents,
    ) {
    }
}
```
:::warning
The event needs all data which is relevant the aggregate to be used since all past event will not be loaded!
Keep this in mind if you want to use this feature.
:::

:::note
This impacts only the aggregate loaded by the repository. Subscriptions will still receive all events.
:::

:::tip
You can combine this feature with the snapshot feature to increase the performance even more.
:::

## Dynamic Consistency Boundary

Projections of the [dynamic consistency boundary](dynamic-consistency-boundary.md) also respect `#[SplitStream]`.
If a projection has an apply method for such an event,
the decision model is only built from the last of these events matching the projection's tags onwards.
No configuration is needed and nothing is archived, other projections and subscriptions still see all events.

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class Balance extends BasicProjection
{
    public function __construct(
        private readonly BankAccountId $bankAccountId,
    ) {
    }

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["bank_account:{$this->bankAccountId->toString()}"];
    }

    #[Apply]
    public function applyBalanceReported(int $state, BalanceReported $event): int
    {
        return $event->balanceInCents;
    }

    #[Apply]
    public function applyMoneyDeposited(int $state, MoneyDeposited $event): int
    {
        return $state + $event->amountInCents;
    }
}
```
:::note
Only the split events of the projection itself are considered.
A `BalanceReported` of another bank account does not cut off the history of this one,
and other projections in the same decision model still get all events they need.
:::

If a projection handles a split event but needs the whole history anyway, for example to count them,
you can opt out by overriding `splitEvents`.

```php
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class NumberOfBalanceReports extends BasicProjection
{
    // ...

    /** @return list<class-string> */
    public function splitEvents(): array
    {
        return [];
    }
}
```

## Learn more

* [How to use message decorator](message-decorator.md)
* [How to define events](events.md)
* [How to define aggregates](aggregate.md)
* [How to store and load aggregates](repository.md)
* [How to use snapshots](snapshots.md)
