# Aggregate

TODO: Aggregate Root definition

An AggregateRoot has to inherit from `AggregateRoot` and implement the method` aggregateRootId`.
Events will be added later, but the following is enough to make it executable:

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    public function aggregateRootId(): string
    {
        // todo
    }
    
    public static function create(string $id): self 
    {
        // todo
    }
}
```

We use a so-called named constructor here to create an object of the AggregateRoot.
The constructor itself is protected and cannot be called from outside.
But it is possible to define different named constructors for different usecases like `createFromRegistration`.

After the basic structure for an aggregate is in place, it could theoretically be saved:

```php
<?php

declare(strict_types=1);

namespace App\Profile\Handler;

use App\Profile\Command\CreateProfile;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;

final class CreateProfileHandler extends AggregateRoot
{
    private Repository $profileRepository;

    public function __construct(Repository $profileRepository) 
    {
        $this->profileRepository = $profileRepository;
    }
    
    public function __invoke(CreateProfile $command): void
    {
        $profile = Profile::create($command->id());
        
        $this->profileRepository->save($profile);
    }
}
```

If you look in the DB now, you would see that nothing has been saved.
This is because only events are stored in the database and as long as no events exist,
nothing happens.

Info: A CommandBus system is not necessary, only recommended.
The interaction can also easily take place in a controller or service.

Info: You can find more about repositories in the chapter `Repository`.

## Event

Information is only stored in the form of events.
These events are also used again to rebuild the current state of the aggregate.

### create aggregate

In order that an aggregate is actually saved, at least one event must exist in the DB.
A "Create" event is ideal here:

```php
<?php

declare(strict_types=1);

namespace App\Profile\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id, string $name): AggregateChanged
    {
        return self::occur(
            $id, 
            [
                'id' => $id,
                'name' => $name
            ]
            );
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }
    
    public function name(): string 
    {
        return $this->payload['name'];
    }
}
```

We recommend using named constructors and methods with typehints,
so that handling becomes easier and less error-prone.

An event must receive the AggregateRoot id and the payload.
The payload must be serializable and non-serializable as json.
In other words, it can only consist of simple data types (no objects).

After we have defined the event, we have to adapt the creation of the profile:

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use App\Profile\Event\ProfileCreated;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function create(string $id, string $name): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $name));

        return $self;
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->name = $event->name();
    }
}
```

Wir haben hier das Event in `create` erzeugt 
und dieses Event mit der Methode `record` gemerkt.

Des Weiteren haben wir eine `applyProfileCreated` Methode, die dazu dient den State anzupassen.
Das AggregateRoot sucht sich mithilfe des Event Short Names `ProfileCreated` die richtige Methode,
indem ein `apply` vorne hinzufügt.

Vorsicht: Wenn so eine Methode nicht existiert wird das verarbeiten übersprungen.
Manche Events verändern nicht den State (wenn nicht nötig), 
sondern werden ggfs. nur in Projections verwendet.

Nachdem ein event mit `->record()` registriert wurde, wird sofort die dazugehörige apply Methode ausgeführt-
Sprich, nach diesem Call ist der State dem entsprechend schon aktualisiert.

### modify aggregate

Um Aggregate nachträglich zu verändern, müssen nur weitere Events definiert werden.
Zb. können wir auch den Namen ändern.

```php
<?php

declare(strict_types=1);

namespace App\Profile\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class NameChanged extends AggregateChanged
{
    public static function raise(string $id, string $name): AggregateChanged
    {
        return self::occur(
            $id,
            [
                'name' => $name
            ]
        );
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }
    
    public function name(): string 
    {
        return $this->payload['name'];
    }
}
```

Nachdem wir das Event definiert haben, können wir unser Aggregat erweitern.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use App\Profile\Event\ProfileCreated;
use App\Profile\Event\NameChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function create(string $id, string $name): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $name));

        return $self;
    }
    
    public function changeName(string $name): void 
    {
        $this->record(NameChanged::raise($this->id, $name));
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->name = $event->name();
    }
    
    protected function applyNameChanged(NameChanged $event): void 
    {
        $this->name = $event->name();
    }
}
```

Auch hierfür fügen wir eine Methode hinzu, um das Event zu registrieren.
Und eine apply Methode, um das ganze auszuführen.

Das ganze können wir dann wie folgt verwenden.

```php
<?php

declare(strict_types=1);

namespace App\Profile\Handler;

use App\Profile\Command\ChangeName;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;

final class ChangeNameHandler extends AggregateRoot
{
    private Repository $profileRepository;

    public function __construct(Repository $profileRepository) 
    {
        $this->profileRepository = $profileRepository;
    }
    
    public function __invoke(ChangeName $command): void
    {
        $profile = $this->profileRepository->load($command->id());
        $profile->changeName($command->name());
    
        $this->profileRepository->save($profile);
    }
}
```

Hier wird das Aggregat geladen, indem alle Events aus der Datenbank geladen wird.
Diese Events werden dann wieder mit der apply Methoden ausgeführt, um den Aktuellen State aufzubauen.
Das alles passiert in der load Methode automatisch.

Daraufhin wird `$profile->changeName()` mit dem neuen Namen aufgerufen. 
Intern wird das Event `NameChanged` geworfen und als nicht "gespeichertes" Event gemerkt.

Zum Schluss wird die `save()` Methode aufgerufen, 
die wiederrum alle nicht gespeicherte Events aus dem Aggregate zieht
und diese dann in die Datenbank speichert.

## business rules

Business Rules müssen immer in den Methoden passieren, die die Events werfen. 
Sprich, in unserem Fall in `create` oder in `changeName` Methoden.

In den Apply Methoden darf nicht mehr überprüft werden, ob die Aktion Valide ist,
da das Event schon passiert ist. Außerdem können diese Events schon in der Datenbank sein,
und somit würde der State aufbau nicht mehr möglich sein. 

Außerdem dürfen in den Apply Methoden keine weiteren Events geworfen werden,
da diese Methoden immer verwendet werden, um den aktuellen State aufzubauen.
Das hätte sonst die Folge, dass beim Laden immer neue Evens erzeugt werden.
Wie Abhängigkeiten von Events implementiert werden können, steht weiter unten.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use App\Profile\Event\ProfileCreated;
use App\Profile\Event\NameChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    private string $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    // ...
    
    public function name(): string 
    {
        return $this->name;
    }
    
    public function changeName(string $name): void 
    {
        if (strlen($name) < 3) {
            throw new NameIsToShortException($name);
        }
    
        $this->record(NameChanged::raise($this->id, $name));
    }
    
    protected function applyNameChanged(NameChanged $event): void 
    {
        $this->name = $event->name();
    }
}
```

Diese Regel, mit der länge des Namens, ist derzeit nur in changeName definiert. 
Damit diese Regel auch beim erstellen greift, muss diese entweder auch in `create` implementiert werden
oder besser, man erstellt ein Value Object dafür, um dafür zu sorgen, dass diese Regel eingehalten wird.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

final class Name
{
    private string $value;
    
    public function __construct(string $value) 
    {
        if (strlen($value) < 3) {
            throw new NameIsToShortException($value);
        }
        
        $this->value = $value;
    }
    
    public function toString(): string 
    {
        return $this->value;
    }
}
```

Den Name Value Object kann man dann wie folgt verwenden.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use App\Profile\Event\ProfileCreated;
use App\Profile\Event\NameChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    private string $id;
    private Name $name;

    public function aggregateRootId(): string
    {
        return $this->id;
    }
    
    public static function create(string $id, Name $name): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $name));

        return $self;
    }
    
    // ...
    
    public function name(): Name 
    {
        return $this->name;
    }
    
    public function changeName(Name $name): void 
    {
        $this->record(NameChanged::raise($this->id, $name));
    }
    
    protected function applyNameChanged(NameChanged $event): void 
    {
        $this->name = $event->name();
    }
}
```

Damit das ganze auch funktioniert, müssen wir unser Event noch anpassen, 
damit es als json serialisierbar ist.

```php
<?php

declare(strict_types=1);

namespace App\Profile\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class NameChanged extends AggregateChanged
{
    public static function raise(string $id, Name $name): AggregateChanged
    {
        return self::occur(
            $id,
            [
                'name' => $name->toString()
            ]
        );
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }
    
    public function name(): Name 
    {
        return new Name($this->payload['name']);
    }
}
```

Es gibt auch die Fälle, dass Regeln abhängig vom State definiert werden müssen. 
Manchmal auch von States, die erst in der Methode zustande kommen.
Das ist kein Problem, da die apply Methoden immer sofort ausgeführt wird.

```php
<?php

declare(strict_types=1);

namespace App\Hotel;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Hotel extends AggregateRoot
{
    private const SIZE = 5;

    private int $people;
    
    // ...
    
    public function book(string $name): void 
    {
        if ($this->people === self::SIZE) {
            throw new NoPlaceException($name);
        }
        
        $this->record(BookRoom::raise($this->id, $name));
        
        if ($this->people === self::SIZE) {
            $this->record(FullyBooked::raise($this->id));
        }
    }
    
    protected function applyBookRoom(BookRoom $event): void 
    {
        $this->people++;
    }
}
```

In diesem Fall schmeißen wir ein zusätzliches Event, wenn unser Hotel ausgebucht ist,
um weitere Systeme zu informieren. ZB. unsere Webseite mithilfe von einer Projection
oder ein fremdes System, um keine Buchungen mehr zu erlauben.

Denkbar wäre auch, dass hier nachträglich eine Exception geschmissen wird.
Da erst bei der save Methode die Events wirklich gespeichert werden, 
kann hier ohne weiteres darauf reagiert werden, ohne dass ungewollt Daten verändert werden.

## override apply methode

Wenn die standard Implementierung aus gründen nicht reicht oder zum Umständlich ist,
dann kann man diese auch überschreiben. Hier findest du ein kleines Beispiel.

```php
<?php

declare(strict_types=1);

namespace App\Profile;

use App\Profile\Event\ProfileCreated;
use App\Profile\Event\NameChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Profile extends AggregateRoot
{
    //...
    protected function apply(AggregateChanged $event): void
    {
        switch ($event::class) {
            case ProfileCreated::class:
                $this->id = $event->profileId();
                $this->name = $event->name();
                break;
            class NameChanged::class: 
                $this->name = $event->name();
                break;
        }
    }
}
```

