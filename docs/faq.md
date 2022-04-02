# FAQ

Here you will find some answers to the most common questions 
that did not fit in the actual documentation.

* [How can i generate an aggregate id?](#how-can-i-generate-an-aggregate-id)
* [How can i improve the handling of the repositories?](#how-can-i-improve-the-handling-of-the-repositories)

## How can i generate an aggregate id?

A UUID can be generated for the `aggregateId`. There are two popular libraries that can be used:

* [ramsey/uuid](https://github.com/ramsey/uuid)
* [symfony/uid](https://symfony.com/doc/current/components/uid.html)

The `aggregate` does not care how the id is generated, since only an aggregate-wide unique string is expected here.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Profile extends AggregateRoot
{
    private UuidInterface $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
    
    public function id(): UuidInterface 
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function create(string $name): self
    {
        $id = Uuid::uuid4();
    
        $self = new self();
        $self->recordThat(ProfileCreated::raise($id, $name));

        return $self;
    }
    
    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void 
    {
        $this->id = $event->profileId();
        $this->name = $event->name();
    }
}
```

Or even better, you create your own aggregate-specific id class.
This allows you to ensure that the correct id is always used.
The whole thing looks like this:

```php
use Ramsey\Uuid\Uuid;

class ProfileId 
{
    private string $id;
    
    public function __constructor(string $id) 
    {
        $this->id = $id;
    }
    
    public static function generate(): self 
    {
        return new self(Uuid::uuid4()->toString());
    }
    
    public function toString(): string 
    {
        return $this->id;
    }
}
```

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;

use Ramsey\Uuid\UuidInterface;

final class Profile extends AggregateRoot
{
    private ProfileId $id;
    private string $name;

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
    
    public function id(): ProfileId 
    {
        return $this->id;
    }
    
    public function name(): string 
    {
        return $this->name;
    }

    public static function create(string $name): self
    {
        $id = ProfileId::generate();
    
        $self = new self();
        $self->recordThat(ProfileCreated::raise($id, $name));

        return $self;
    }
    
    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void 
    {
        $this->id = $event->profileId();
        $this->name = $event->name();
    }
}
```

## How can i improve the handling of the repositories?

In clean code you want to have explicit type hints for the repositories 
so that you don't accidentally use the wrong repository. 
It would also help in frameworks with a dependency injection container, 
as this allows the services to be autowired. 
However, you cannot inherit from our repository implementations. 
Instead, you just have to wrap these repositories. 
This also gives you more type security.

```php
use Patchlevel\EventSourcing\Repository\Repository;

class ProfileRepository 
{
    private Repository $repository;

    public function __constructor(Repository $repository) 
    {
        $this->repository = $repository;
    }
    
    public function load(ProfileId $id): Profile 
    {
        return $this->repository->load($id->toString());
    }
    
    public function save(Profile $profile): void 
    {
        return $this->repository->save($profile);
    }
    
    public function has(ProfileId $id): bool 
    {
        return $this->repository->has($id->toString());
    }
}
```
