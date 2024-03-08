# Subscriptions

With `subscriptions` you can transform your data optimized for reading.
Subscriptions can be adjusted, deleted or rebuilt at any time.
This is possible because the event store remains untouched
and everything can always be reproduced from the events.

A subscription can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Subscriber

To create a subscription you need a subscriber with a unique ID named `subscriberId`.
This subscriber is responsible for a specific subscription.
To do this, you can use the `Subscriber` attribute.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;

#[Subscriber('profile_1')]
final class ProfileSubscriber
{
    use SubscriberUtil;

    public function __construct(
        private readonly Connection $connection
    ) {
    }
}
```

!!! tip

    Add a version as suffix to the `subscriberId`, 
    so you can increment it when the subscription changes.
    Like `profile_1` to `profile_2`.

!!! warning

    MySQL and MariaDB don't support transactions for DDL statements.
    So you must use a different database connection for your subscriptions.

### Subscribe

A subscriber can subscribe any number of events.
In order to say which method is responsible for which event, you need the `Subscribe` attribute.
There you can pass the event class to which the reaction should then take place.
The method itself must expect a `Message`, which then contains the event. 
The method name itself doesn't matter.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;

#[Subscriber('profile_1')]
final class ProfileSubscriber
{
    use SubscriberUtil;
    
    // ...
    
    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();
    
        $this->connection->executeStatement(
            "INSERT INTO {$this->table()} (id, name) VALUES(?, ?);",
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name
            ]
        );
    }
    
    private function table(): string 
    {
        return 'subscription_' . $this->subscriptionId();
    }
}
```

!!! warning

    You have to be careful with actions because in default it will be executed from the start of the event stream.
    Even if you change the SubscriptionId, it will run again from the start.

!!! note

    You can subscribe to multiple events on the same method or you can use "*" to subscribe to all events.
    More about this can be found [here](./event_bus.md#listener).

!!! tip

    If you are using psalm then you can install the event sourcing [plugin](https://github.com/patchlevel/event-sourcing-psalm-plugin) 
    to make the event method return the correct type.

### Setup and Teardown

Subscribers can have one `setup` and `teardown` method that is executed when the subscription is created or deleted.
For this there are the attributes `Setup` and `Teardown`. The method name itself doesn't matter.
In some cases it may be that no schema has to be created for the subscription,
as the target does it automatically, so you can skip this.

```php
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;

#[Subscriber('profile_1')]
final class ProfileSubscriber
{
    use SubscriberUtil;
    
    // ...

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement(
            "CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL);"
        );
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    private function table(): string 
    {
        return 'subscription_' . $this->subscriptionId();
    }
}
```

!!! warning

    If you change the `subscriberID`, you must also change the table/collection name.
    Otherwise the table/collection will conflict with the old subscription.

!!! note

    Most databases have a limit on the length of the table/collection name.
    The limit is usually 64 characters.

!!! tip

    You can also use the `SubscriberUtil` to build the table/collection name.

### Read Model

You can also implement your read model here. 
You can offer methods that then read the data and put it into a specific format.

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;

#[Subscriber('profile_1')]
final class ProfileSubscriber
{
    use SubscriberUtil;

    // ...

    /**
     * @return list<array{id: string, name: string}>
     */
    public function getProfiles(): array 
    {
        return $this->connection->fetchAllAssociative("SELECT id, name FROM {$this->table()};");
    }
    
    private function table(): string 
    {
        return 'subscription_' . $this->subscriptionId();
    }
}
```

!!! tip

    You can also use the `SubscriberUtil` to build the table/collection name.

### Versioning

As soon as the structure of a subscription changes, or you need other events from the past,
the `subscriberId` must be change or increment.

Otherwise, the subscription engine will not recognize that the subscription has changed and will not rebuild it.
To do this, you can add a version to the `subscriberId`:

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;

#[Subscriber('profile_2')]
final class ProfileSubscriber
{
   // ...
}
```

!!! warning

    If you change the `subscriberID`, you must also change the table/collection name.
    Otherwise the table/collection will conflict with the old subscription.

### Grouping

You can also group subscribers and address these to the subscription engine.
This is useful if you want to run subscribers in different processes or on different servers.

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;

#[Subscriber('profile_1', group: 'a')]
final class ProfileSubscriber
{
   // ...
}
```

!!! note

    The default group is `default` and the subscription engine takes all groups if none are given to him.

### Run Mode

The run mode determines how the subscriber should behave when it is booted.
There are three different modes:

#### From Beginning

This is the default mode. 
The subscriber will start from the beginning of the event stream and process all events.

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('welcome_email', runMode: RunMode::FromBeginning)]
final class WelcomeEmailSubscriber
{
   // ...
}
```

#### From Now

Certain subscribers operate exclusively on post-release events, disregarding historical data.
This is useful for subscribers that are only interested in events that occur after a certain point in time.
As example, a welcome email subscriber that only wants to send emails to new users.

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('welcome_email', runMode: RunMode::FromNow)]
final class WelcomeEmailSubscriber
{
   // ...
}
```

#### Once

This mode is useful for subscribers that only need to run once.
This is useful for subscribers to create reports or to migrate data.

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('migration', runMode: RunMode::Once)]
final class MigrationSubscriber
{
   // ...
}
```

## Subscription Engine

The subscription engine manages individual subscribers and keeps the subscriptions running.
Internally, the subscription engine does this by tracking where each subscriber is in the event stream
and keeping all subscriptions up to date.
He also takes care that new subscribers are booted and old ones are removed again.
If something breaks, the subscription engine marks the individual subscriptions as faulty.

!!! tip

    The Subscription Engine was inspired by the following two blog posts:

    * [Projection Building Blocks: What you'll need to build projections](https://barryosull.com/blog/projection-building-blocks-what-you-ll-need-to-build-projections/)
    * [Managing projectors is harder than you think](https://barryosull.com/blog/managing-projectors-is-harder-than-you-think/)

## Subscription ID

The subscription ID is taken from the associated subscriber and corresponds to the subscriber ID.
Unlike the subscriber ID, the subscription ID can no longer change.
If the Subscriber ID is changed, a new subscription will be created with this new subscriber ID.
So there are two subscriptions, one with the old subscriber ID and one with the new subscriber ID.

## Subscription Position

Furthermore, the position in the event stream is stored for each subscription.
So that the subscription engine knows where the subscription stopped and must continue.

## Subscription Status

There is a lifecycle for each subscription.
This cycle is tracked by the subscription engine.

``` mermaid
stateDiagram-v2
    direction LR
    [*] --> New
    New --> Booting
    New --> Error
    Booting --> Active
    Booting --> Paused
    Booting --> Finished
    Booting --> Error
    Active --> Paused
    Active --> Finished
    Active --> Outdated
    Active --> Error
    Paused --> New
    Paused --> Booting
    Paused --> Active
    Paused --> Outdated
    Paused --> [*]
    Finished --> Active
    Finished --> Outdated
    Error --> New
    Error --> Booting
    Error --> Active
    Error --> Paused
    Error --> [*]
    Outdated --> Active
    Outdated --> [*]
```

### New

A subscription is created and "new" if a subscriber exists with an ID that is not yet tracked.
This can happen when either a new subscriber has been added, the `subscriber id` has changed
or the subscription has been manually deleted from the subscription store.

### Booting

Booting status is reached when the boot process is invoked.
In this step, the "setup" method is called on the subscription, if available.
And the subscription is brought up to date, depending on the mode.
When the process is finished, the subscription is set to active.

### Active

The active status describes the subscriptions currently being actively managed by the subscription engine.
These subscriptions have a subscriber, follow the event stream and should be up-to-date.

## Paused

A subscription can manually be paused. It will then no longer be updated by the subscription engine.
This can be useful if you want to pause a subscription for a certain period of time.
You can also reactivate the subscription if you want so that it continues.

### Finished

A subscription is finished if the subscriber has the mode `RunMode::Once`.
This means that the subscription is only run once and then set to finished if it reaches the end of the event stream.
You can also reactivate the subscription if you want so that it continues.

### Outdated

If an active or finished subscription exists in the subscription store
that does not have a subscriber in the source code with a corresponding subscriber ID,
then this subscription is marked as outdated.
This happens when either the subscriber has been deleted
or the subscriber ID of a subscriber has changed.
In the last case there should be a new subscription with the new subscriber ID.

An outdated subscription does not automatically become active again when the subscriber exists again.
This happens, for example, when an old version was deployed again during a rollback.

There are two options to reactivate the subscription:

* Reactivate the subscription, so that the subscription is active again.
* Remove the subscription and rebuild it from scratch.

### Error

If an error occurs in a subscriber, then the target subscription is set to Error.
This can happen in the create process, in the boot process or in the run process.
This subscription will then no longer boot/run until the subscription is reactivate or retried.

The subscription engine has a retry strategy to retry subscriptions that have failed.
It tries to reactivate the subscription after a certain time and a certain number of attempts.
If this does not work, the subscription is set to error and must be manually reactivated.

There are two options here:

* Reactivate the subscription, so that the subscription is in the previous state again.
* Remove the subscription and rebuild it from scratch.

## Setup

In order for the subscription engine to be able to do its work, you have to assemble it beforehand.

### Subscription Store

The Subscription Engine uses a subscription store to store the status of each subscription.
We provide a Doctrine implementation of this by default.

```php
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;

$subscriptionStore = new DoctrineSubscriptionStore($connection);
```

So that the schema for the subscription store can also be created,
we have to tell the `SchemaDirector` our schema configuration.
Using `ChainSchemaConfigurator` we can add multiple schema configurators.
In our case they need the `SchemaConfigurator` from the event store and subscription store.

```php
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector(
    $connection
    new ChainDoctrineSchemaConfigurator([
        $eventStore,
        $subscriptionStore
    ]),
);
```

!!! note

    You can find more about schema configurator [here](./store.md) 

### Retry Strategy

The subscription engine uses a retry strategy to retry subscriptions that have failed.
Our default strategy can be configured with the following parameters:

* `baseDelay` - The base delay in seconds.
* `delayFactor` - The factor by which the delay is multiplied after each attempt.
* `maxAttempts` - The maximum number of attempts.

```php
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;

$retryStrategy = new ClockBasedRetryStrategy(
    baseDelay: 5,
    delayFactor: 2,
    maxAttempts: 5,
);
```

!!! tip

    You can reactivate the subscription manually or remove it and rebuild it from scratch.

### Subscriber Accessor

The subscriber accessor is responsible for providing the subscribers to the subscription engine.
We provide a metadata subscriber accessor repository by default.

```php
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;

$subscriberAccessorRepository = new MetadataSubscriberAccessorRepository([$subscriber1, $subscriber2, $subscriber3]);
```

### Subscription Engine

Now we can create the subscription engine and plug together the necessary services.
The event store is needed to load the events, the Subscription Store to store the subscription state 
and the respective subscribers. Optionally, we can also pass a retry strategy.

```php
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;

$subscriptionEngine = new DefaultSubscriptionEngine(
    $eventStore,
    $subscriptionStore,
    $subscriberAccessorRepository,
    $retryStrategy,
);
```

## Usage

The Subscription Engine has a few methods needed to use it effectively.
A `SubscriptionEngineCriteria` can be passed to all of these methods to filter the respective subscribers.

```php
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;

$criteria = new SubscriptionEngineCriteria(
    ids: ['profile_1', 'welcome_email'],
    groups: ['default']
);
```

!!! note

    An `OR` check is made for the respective criteria and all criteria are checked with an `AND`.

### Boot

So that the subscription engine can manage the subscriptions, they must be booted.
In this step, the structures are created for all new subscriptions.
The subscriptions then catch up with the current position of the event stream.
When the subscriptions are finished, they switch to the active state.

```php
$subscriptionEngine->boot($criteria);
```

### Run

All active subscriptions are continued and updated here.

```php
$subscriptionEngine->run($criteria);
```

### Teardown

If subscriptions are outdated, they can be cleaned up here.
The subscription engine also tries to remove the structures created for the subscription.

```php
$subscriptionEngine->teardown($criteria);
```

### Remove

You can also directly remove a subscription regardless of its status.
An attempt is made to remove the structures, but the entry will still be removed if it doesn't work.

```php
$subscriptionEngine->remove($criteria);
```

### Reactivate

If a subscription had an error, you can reactivate it.
As a result, the subscription gets the status active again and is then kept up-to-date again by the subscription engine.

```php
$subscriptionEngine->reactivate($criteria);
```

### Pause

Pausing a subscription is also possible.
The subscription will then no longer be updated by the subscription engine.
You can reactivate the subscription if you want so that it continues.

```php
$subscriptionEngine->pause($criteria);
```

### Status

To get the current status of all subscriptions, you can get them using the `subscriptions` method.

```php
$subscriptions = $subscriptionEngine->subscriptions($criteria);

foreach ($subscriptions as $subscription) {
    echo $subscription->status();
}
```

## Learn more

* [How to use CLI commands](./cli.md)
* [How to use Pipeline](./pipeline.md)
* [How to use Event Bus](./event_bus.md)
* [How to Test](./testing.md)