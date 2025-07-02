# Upgrade 4.0

## Subscription

* The constructor of the `SubscriptionEngine` class has been changed
* * Instead of passing a `Store` instance, you now need to pass a `MessageLoader` instance.
* * Instead of passing a `RetryStrategy` instance, you now need to pass a `RetryStrategyRepository` instance.