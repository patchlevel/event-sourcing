# Pipeline

Ein Store ist immutable, sprich es darf nicht mehr nachträglich verändert werde.
Dazu gehört sowohl das Manipulieren von Events als auch das Löschen.

Stattdessen kann man den Store duplizieren und dabei die Events manipulieren.
Somit bleibt der alte Store unberührt und man kann vorher den neuen Store durchtesten,
ob die Migration funktioniert hat.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$pipeline = new Pipeline(
    new StoreSource($oldStore),
    new StoreTarget($newStore),
    [
        new ExcludeEventMiddleware([PrivacyAdded::class]),
        new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
            return NewVisited::raise($oldVisited->profileId());
        }),
        new RecalculatePlayheadMiddleware(),
    ]
);
```

Oder man kann eine oder mehrere Projection neu erstellen, 
wenn entweder neue Projection existieren oder bestehende verändert wurden.

```php
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;

$pipeline = new Pipeline(
    new StoreSource($store),
    new ProjectionTarget($projection)
);
```

Das Prinzip bleibt dabei gleich. Es gibt eine Source, woher die Daten kommen.
Ein Target wohin die Daten fließen sollen. 
Und beliebig viele Middlewares um mit den Daten vorher irgendetwas anzustellen.

## Source

Als Erstes braucht man eine Quelle. Derzeit gibt es nur den `StoreSource` und `InMemorySource` als Quelle.
Ihr könnt aber jederzeit eigene Sources hinzufügen, 
indem ihr das Interface `Patchlevel\EventSourcing\Pipeline\Source\Source` implementiert.
Hier könnt ihr zB. auch von anderen Event-Sourcing Systeme migrieren.

### Store

Der StoreSource ist die standard Quelle um alle Events aus der Datenbank zu laden.

```php
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;

$source = new StoreSource($store);
```

### In Memory

Den InMemorySource kann dazu verwendet werden, um tests zu schreiben.

```php
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;

$source = new InMemorySource([
    new EventBucket(
        Profile::class,
        ProfileCreated::raise(Email::fromString('d.a.badura@gmail.com'))->recordNow(0),
    ),
    // ...
]);
```

## Target

Ziele dienen dazu, um die Daten am Ende des Process abzuarbeiten. 
Das kann von einem anderen Store bis hin zu Projektionen alles sein.

### Store

Als Target kann man einen neuen Store verwendet werden. 
Hierbei ist es egal, ob der vorherige Store ein SingleTable oder MultiTable war.
Sprich, man kann auch nachträglich zwischen den beiden Systemen migrieren.

Wichtig ist aber, dass nicht derselbe Store verwendet wird!
Ein Store ist immutable und darf nur dupliziert werden!

```php
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;

$target = new StoreTarget($store);
```

### Projection

Eine Projection kann man auch als Target verwenden, 
um zum beispiel eine neue Projection aufzubauen oder eine Projection neu zu bauen.

```php
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;

$target = new ProjectionTarget($projection);
```

### Projection Repository

Wenn man gleich alle Projections neu bauen oder erzeugen möchte,
dann kann man auch das ProjectionRepositoryTarget verwenden.

```php
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionRepositoryTarget;

$target = new ProjectionRepositoryTarget($projectionRepository);
```

### In Memory

Für test zwecke kann man hier auch den InMemoryTarget verwenden.

```php
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;

$target = new InMemoryTarget();

// run pipeline

$buckets = $target->buckets();
```

## Middlewares

Um Events bei dem Prozess zu manipulieren, löschen oder zu erweitern, kann man Middelwares verwenden.
Dabei ist wichtig zu wissen, dass einige Middlewares eine recalculation vom playhead erfordert.
Das ist eine Nummerierung der Events, die aufsteigend sein muss. 
Ein dem entsprechenden Hinweis wird bei jedem Middleware mitgeliefert.

### exclude

Mit dieser Middleware kann man bestimmte Events ausschließen.

Wichtig: ein recalculation vom Playhead ist notwendig!

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;

$middleware = new ExcludeEventMiddleware([EmailChanged::class]);
```

### include


Mit dieser Middleware kann man nur bestimmte Events erlauben.

Wichtig: ein recalculation vom Playhead ist notwendig!

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware;

$middleware = new IncludeEventMiddleware([ProfileCreated::class]);
```

### filter

Wenn die standard Filter Möglichkeiten nicht ausreichen, kann man auch einen eigenen Filter schreiben.
Dieser verlangt ein boolean als Rückgabewert. `true` um Events zu erlauben, `false` um diese nicht zu erlauben.

Wichtig: ein recalculation vom Playhead ist notwendig!

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\Middleware\FilterEventMiddleware;

$middleware = new FilterEventMiddleware(function (AggregateChanged $event) {
    if (!$event instanceof ProfileCreated) {
        return true;
    }
    
    return $event->allowNewsletter();
});
```


### replace

Wenn man ein Event ersetzen möchte, kann man den ReplaceEventMiddleware verwenden.
Als ersten Parameter muss man die Klasse definieren, die man ersetzen möchte.
Und als zweiten Parameter ein Callback, 
dass den alten Event erwartet und ein neues Event zurückliefert.
Die Middleware übernimmt dabei den playhead und recordedAt informationen.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;

$middleware = new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
    return NewVisited::raise($oldVisited->profileId());
});
```

### class rename

Wenn ein Mapping nicht notwendig ist und man nur die Klasse umbenennen möchte 
(zB. wenn Namespaces sich geändert haben), dann kann man den ClassRenameMiddleware verwenden.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware;

$middleware = new ClassRenameMiddleware([
    OldVisited::class => NewVisited::class
]);
```

### recalculate playhead

Mit dieser Middleware kann man den Playhead neu berechnen lassen. 
Dieser muss zwingend immer aufsteigend sein, damit das System weiter funktioniert.
Man kann diese Middleware als letztes hinzufügen.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;

$middleware = new RecalculatePlayheadMiddleware();
```

### chain

Wenn man seine Middlewares Gruppieren möchte, kann man dazu eine oder mehrere ChainMiddlewares verwenden.

```php
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;

$middleware = new ChainMiddleware([
    new ExcludeEventMiddleware([EmailChanged::class]),
    new RecalculatePlayheadMiddleware()
]);
```
