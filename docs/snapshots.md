# Snapshots

Some aggregates can have a large number of events. 
This is not a problem if there are a few hundred. 
But if the number gets bigger at some point, then loading and rebuilding can become slow. 
The `snapshot` system can be used to control this.

Normally, the events are all executed again on the aggregate in order to rebuild the current state. 
With a `snapshot`, we can shorten the way in which we temporarily save the current state of the aggregate.
When loading it is checked whether the snapshot exists. 
If a hit exists, the aggregate is built up with the help of the snapshot. 
A check is then made to see whether further events have existed since the snapshot 
and these are then also executed on the aggregate. 
Here, however, only the last events are loaded from the database and not all.

## Configuration

To use the snapshot system, the `SnapshotRepository` must be used. 
In addition, a `SnapshotStore` must then be given.

```php
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);

$repository = new SnapshotRepository($store, $eventStream, Profile::class, $snapshotStore);
```

> :book: You can read more about Repository [here](./repository.md).

So that the state can also be cached, the aggregate must be taught how to `serialize` and `deserialize` its state.
To do this, the aggregate must inherit from the `SnapshotableAggregateRoot`
instead of the `AggregateRoot` and implement the necessary methods.

```php
use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;

final class Profile extends SnapshotableAggregateRoot
{
    // ...
    
    protected function serialize(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    protected static function deserialize(array $payload): static
    {
        $self = new static();
        $self->id = $payload['id'];

        return $self;
    }
}
```

> :warning: In the end it has to be possible to serialize it as json.

## stores

We offer a few `SnapshotStore` implementations that you can use.
But not a direct implementation of a cache. 
There are many good libraries out there that address this problem, 
and before we reinvent the wheel, choose one of them. 
Since there is a psr-6 and psr-16 standard, there are plenty of libraries. 
Here are a few listed:

* [symfony cache](https://symfony.com/doc/current/components/cache.html)
* [laminas cache](https://docs.laminas.dev/laminas-cache/)
* [scrapbook](https://www.scrapbook.cash/)

### psr6

A `Psr6SnapshotStore`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-6/).

```php
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;

$snapshotStore = new Psr6SnapshotStore($cache);
```

### psr16

A `Psr16SnapshotStore`, the associated documentation can be found [here](https://www.php-fig.org/psr/psr-16/).

```php
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);
```

### in memory

A `InMemorySnapshotStore` that can be used for test purposes.

```php
use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;

$snapshotStore = new InMemorySnapshotStore();
```

### batch store (since v1.2)

Any other store can be wrapped with the `BatchSnapshotStore`. 
It checks how many events away the last snapshot is to the current one. 
Everything that is under the threshold is not written to the SnapshotStore. 
As soon as the difference exceeds the specified value, the writing process is started.
This prevents the cache from being slowed down by too many write processes.

```php
use Patchlevel\EventSourcing\Snapshot\BatchSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;

$psr6Store = new Psr6SnapshotStore($cache);
$snapshotStore = new BatchSnapshotStore($psr6Store, 20);
```

> :book: It uses an internal cache that you can clear with the `freeMemory` method.