# Repository


## Create


### Default Repository

```php
use Patchlevel\EventSourcing\Repository\Repository;

$repository = new Repository($store, $eventStream, Profile::class);
```

### Snapshot Repository

```php
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;

$snapshot = new Psr6SnapshotStore(/* ... */);
$repository = new SnapshotRepository($store, $eventStream, Profile::class, $snapshot);
```

> :warning: The aggregate must inherit from the SnapshotableAggregateRoot

> :book: You can find out more about snapshots [here](./snapshots.md)

## Usage

### Save

```php
$profile = Profile::create('david.badura@patchlevel.de');

$repository->save($profile);
```

### Load

```php
$profile = $repository->load('229286ff-6f95-4df6-bc72-0a239fe7b284');
```

> :book: The repository ensures that only one instance per aggregate is returned. 
> A strict instance comparison is therefore easily possible.

### Has

```php
if(!$repository->has('229286ff-6f95-4df6-bc72-0a239fe7b284')) {
    // ...
}
```
