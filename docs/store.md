# Store

## Single Table Store

```php
use Patchlevel\EventSourcing\Store\SingleTableStore;

$store = new SingleTableStore(
    $this->connection,
    [
        Profile::class => 'profile'
    ],
    'eventstore'
);
```

## Multi Table Store

```php
use Patchlevel\EventSourcing\Store\MultiTableStore;

$store = new MultiTableStore(
    $this->connection,
    [
        Profile::class => 'profile'
    ],
    'eventstore'
);
```
