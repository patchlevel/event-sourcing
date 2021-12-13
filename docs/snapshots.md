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

So that the state can also be cached, the aggregate must be taught how to `serialize` and `deserialize` its state.
To do this, the aggregate must inherit from the `SnapshotableAggregateRoot`
instead of the `AggregateRoot` and implement the necessary methods.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

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

    protected static function deserialize(array $payload): self
    {
        $self = new self();
        $self->id = $payload['id'];

        return $self;
    }
}
```

> :warning: In the end it has to be possible to serialize it as json.

## stores

We offer a few `SnapshotStore` implementations that you can use.

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