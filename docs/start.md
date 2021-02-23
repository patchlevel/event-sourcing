# Getting Started

```php
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\SingleTableStore;

$profileProjection = new ProfileProjection($this->connection);
$projectionRepository = new DefaultProjectionRepository(
    [$profileProjection]
);

$eventStream = new DefaultEventBus();
$eventStream->addListener(new ProjectionListener($projectionRepository));
$eventStream->addListener(new SendEmailProcessor());

$store = new SingleTableStore(
    $this->connection,
    [Profile::class => 'profile'],
    'eventstore'
);

$repository = new Repository($store, $eventStream, Profile::class);

// create tables
$profileProjection->create();

(new DoctrineSchemaManager())->create($store);

$profile = Profile::create('1');
$repository->save($profile);
```
