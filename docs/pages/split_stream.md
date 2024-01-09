# Splitting the eventstream

In some cases the business has rules which implies an restart of the event stream for an aggregate since the past events
are not relevant for the current state. For example a user decides to end his active subscription and the business rules
says if the user start a new subscription all past events should not be considered anymore. Another case could be a
banking scenario. There the business decides to save the current state every quarter for each banking account.

Not only that some businesses requires such an action it also increases the performance for aggregate which would have a
really long event stream.

## Flagging an event to split the stream

To use this feature you need to add the `SplitStreamDecorator`. You will also need events which will trigger this
action. For that you can use the `#[SplitStream]` attribute. We decided that we are not literallty splitting the stream,
instead we are marking all past events as archived as soon as this event is saved. Then the past events will not be
loaded anymore for building the aggregate. This means that all needed data has to be present in these events which
should trigger the event split.

```php
#[Event('bank_account.month_passed')]
#[SplitStream]
final class MonthPassed
{
    public function __construct(
        #[Normalize(new AccountIdNormalizer())]
        public AccountId $accountId,
        public string $name,
        public int $balanceInCents,
    ) {
    }
}
```

!!! warning

    The event needs all data which is relevant the aggregate to be used since all past event will not be loaded! Keep
    this in mind if you want to use this feature.

!!! note

    This archive flag only impacts the Store::load method which is used the build the aggregate from the stream.
