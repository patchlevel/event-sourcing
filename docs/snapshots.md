# Snapshots

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


```php
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);

$repository = new SnapshotRepository($store, $eventStream, Profile::class, $snapshotStore);
```


## stores

### psr16

```php
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);
```

### psr6

```php
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;

$snapshotStore = new Psr6SnapshotStore($cache);
```

### in memory

```php
use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;

$snapshotStore = new InMemorySnapshotStore();
```