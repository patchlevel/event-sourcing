# Command Bus

You can also use the Command Bus to work with your aggregate root and perform write operations such as creating or editing.
We provide a Command Bus implementation that is sufficient for our use case. 
We only support handlers defined in Aggregate and no other handler services.

## Setup

```php
use Patchlevel\EventSourcing\CommandBus\DefaultCommandBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var RepositoryManager $repositoryManager
 */
$commandBus = DefaultCommandBus::createForAggregateHandlers(
    $aggregateRootRegistry,
    $repositoryManager,
);

$commandBus->dispatch(new CreateProfile($profileId, 'John'));
$commandBus->dispatch(new ChangeProfileName($profileId, 'John Doe'));
```
## Command

```php
final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
```



```php
use Patchlevel\EventSourcing\Attribute\Id;

final class ChangeProfileName
{
    public function __construct(
        #[Id]
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
```
## Handler

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private string $name;

    #[Handle]
    public static function create(CreateProfile $command): self
    {
        $self = new self();

        if (!$nameValidator($command->name) {
            throw new InvalidArgument();
        }

        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    #[Handle]
    public function changeName(ChangeProfileName $command): void
    {
        if (!$nameValidator($command->name) {
            throw new InvalidArgument();
        }

        $this->recordThat(new NameChanged($command->name));
    }

    // ... apply methods
}
```
## Dependency Injection

```php
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\DefaultCommandBus;
use Patchlevel\EventSourcing\CommandBus\ServiceLocator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Psr\Clock\ClockInterface;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var RepositoryManager $repositoryManager
 */
$commandBus = DefaultCommandBus::createForAggregateHandlers(
    $aggregateRootRegistry,
    $repositoryManager,
    new ServiceLocator([
        ClockInterface::class => new SystemClock(),
    ]), // or other psr-11 compatible container
);
```
```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Psr\Clock\ClockInterface;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Handle]
    public static function create(
        CreateProfile $command,
        ClockInterface $clock,
    ): self {
        $self = new self();

        $self->recordThat(new ProfileCreated(
            $command->id,
            $command->name,
            $clock->now(),
        ));

        return $self;
    }

    // ... apply methods
}
```
### Inject

```php
use Patchlevel\EventSourcing\CommandBus\DefaultCommandBus;
use Patchlevel\EventSourcing\CommandBus\ServiceLocator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var RepositoryManager $repositoryManager
 */
$commandBus = DefaultCommandBus::createForAggregateHandlers(
    $aggregateRootRegistry,
    $repositoryManager,
    new ServiceLocator([
        'name_validator' => new NameValidator(),
    ]), // or other psr-11 compatible container
);
```
```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Inject;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Handle]
    public static function create(
        CreateProfile $command,
        #[Inject('name_validator')]
        NameValidator $nameValidator,
    ): self {
        $self = new self();

        if (!$nameValidator($command->name) {
            throw new InvalidArgument();
        }

        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    // ... apply methods
}
```
## Learn more

* [How to use aggregates](aggregate.md)
* [How to use events](events.md)
* [How to use clock](clock.md)
* [How to use aggregate id](aggregate_id.md)
