# Outbox

There is the problem that errors can occur when saving an aggregate or in the individual event listeners.
This means that you either saved an aggregate, but an error occurred in the email listener, so that no email went out.
Or that an email was sent but the aggregate could not be saved.

Both cases are very bad and can only be solved if both the saving of an aggregate
and the dispatching of the events are in a transaction.

The best way to ensure this is to store the events to be dispatched together
with the aggregate in a transaction in the same database.

After the transaction becomes successful, the events can be loaded from the outbox table with a worker
and then dispatched into the correct event bus. As soon as the events have been dispatched,
they are deleted from the outbox table. If an error occurs when dispatching, the whole thing will be retrieved later.

## Configuration

First you have to replace the correct event bus with an outbox event bus.
This stores the events to be dispatched in the database.

```php
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Outbox\OutboxEventBus;

$outboxEventBus = new OutboxEventBus($store);

$repositoryManager = new DefaultRepositoryManager(
    $aggregateRootRegistry,
    $store,
    $outboxEventBus
);
```

And then you have to define the consumer. This gets the right event bus.
It is used to load the events to be dispatched from the database, dispatch the events and then empty the outbox table.

```php
$consumer = new StoreOutboxConsumer($store, $realEventBus);
$consumer->consume();
```

## Using outbox

So that this is also executed in a transaction, you have to make sure that a transaction has also been started.

```php
$store->transactional(function () use ($command, $profileRepository) {
    $profile = Profile::register(
        $command->id(),
        $command->email()
    );

    $profileRepository->save($profile);
});
```

!!! note

    You can find out more about transaction [here](store.md#transaction).

You can also interact directly with the outbox store.

```php
$store->saveOutboxMessage($message);
$store->markOutboxMessageConsumed($message);

$store->retrieveOutboxMessages();
$store->countOutboxMessages()
```

!!! note

    Both single table store and multi table store implement the outbox store.

!!! tip

    Interacting with the outbox store is also possible via the [cli](cli.md).
