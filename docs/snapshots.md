# Snapshots

```php
<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate;

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
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);

$repository = new Repository($store, $eventStream, Profile::class, $snapshotStore);
```
