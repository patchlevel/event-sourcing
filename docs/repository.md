# Repository


## Create

```php
use Patchlevel\EventSourcing\Repository\Repository;

$repository = new Repository($store, $eventStream, Profile::class);
```

## Usage

```php
$profile = Profile::create('david.badura@patchlevel.de');

$repository->save($profile);
```

```php
$profile = $repository->load('229286ff-6f95-4df6-bc72-0a239fe7b284');
```

```php
if(!$repository->has('229286ff-6f95-4df6-bc72-0a239fe7b284')) {
    // ...
}
```

## Snapshots

```php
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;

$snapshotStore = new Psr16SnapshotStore($cache);

$repository = new Repository($store, $eventStream, Profile::class, $snapshotStore);
```

Mehr dazu 
