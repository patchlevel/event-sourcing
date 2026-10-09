# Command Bus

The Command Bus is an optional component in the Event Sourcing library that coordinates the execution of commands.
It allows commands to be forwarded to the appropriate aggregates and their handlers to be invoked.
This promotes a clear separation of responsibilities and simplifies the management of business logic.

## Command

First of all, you need to create a command class.
A command is a simple data transfer object that represents an intention to perform an action.

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
## Handler

Then you need to create a handler class.
A handler is a class that contains the business logic for a command.
It will be invoked when a command is dispatched.
You need to mark the method that handles the command with the `#[Handle]` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Handle;

final class CreateProfileHandler
{
    #[Handle]
    public function __invoke(CreateProfile $command): void
    {
        // handle command
    }
}
```
:::note
To use Service Handler you need to register the handler in the `ServiceHandlerProvider`.
:::

### Multiple Handle Attributes

A method can also have multiple `#[Handle]` attributes.
This is useful if you want to handle different commands with the same method.

```php
use Patchlevel\EventSourcing\Attribute\Handle;

final class CreateProfileHandler
{
    #[Handle(CreateProfile::class)]
    #[Handle(UpdateProfile::class)]
    public function __invoke(object $command): void
    {
        // handle both commands
    }
}
```
### Union Types

You can also use union types to handle multiple commands and the library will automatically detect the commands.

```php
use Patchlevel\EventSourcing\Attribute\Handle;

final class CreateProfileHandler
{
    #[Handle]
    public function __invoke(CreateProfile|UpdateProfile $command): void
    {
        // handle both commands
    }
}
```
### Inheritance

The handler will also be invoked if the command implements an interface or extends a class that the handler expects.

```php
use Patchlevel\EventSourcing\Attribute\Handle;

final class CreateProfileHandler
{
    #[Handle]
    public function __invoke(CommandInterface $command): void
    {
        // handle all commands that implement CommandInterface
    }
}
```
### Aggregate Handler

Another way to handle commands is to use the aggregates themselves.
To do this, you need to mark the method that handles the command with the `#[Handle]` attribute.

:::note
The aggregates themselves are of course not a service.
The AggregateHandlerProvider uses the aggregates to create the handlers for you.
You can find out more about this in the [providers](command-bus.md#provider) section.
:::

#### Create Aggregate

If you want to create a new aggregate, you need to create a static method that returns a new instance of the aggregate.

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
        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    // ... apply methods
}
```
:::tip
You can find more information about [aggregates](aggregate.md).
:::

#### Update Aggregate

If you want to update an existing aggregate,
first you need to mark the `aggregate id` with the `#[Id]` attribute in the command class.
Otherwise, the handler does not know which aggregates should be loaded.

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
Then you need to create a method that changes the aggregate state.
Here too, you need to mark the method with the `#[Handle]` attribute.

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
    public function changeName(ChangeProfileName $command): void
    {
        if (!$nameValidator($command->name)) {
            throw new InvalidArgument();
        }

        $this->recordThat(new NameChanged($command->name));
    }

    // ... apply methods
}
```
:::tip
If you want to automatically initialize an aggregate if it cannot be found in the store,
you can use the [Auto Initialize](aggregate.md#auto-initialize) feature.
:::

#### Inject Service

You can inject services into aggregate handler methods.
Starting with the second parameter, it automatically tries to inject the service using a service locator.
By default, it uses the fully qualified class name from the parameter type hint to find the service.

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
:::note
The service must be registered in the service locator.
:::

:::tip
You can inject multiple services into the handler method.
:::

Or you can inject the service manually using the `#[Inject]` attribute.
There you can specify the service name that should be injected.

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

        if (!$nameValidator($command->name)) {
            throw new InvalidArgument();
        }

        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    // ... apply methods
}
```
:::note
Injection in handler methods is only possible with the `AggregateHandlerProvider`.
:::

## Setup

We provide a `SyncCommandBus` that you can use to dispatch commands.
You need to pass a `HandlerProvider` to the constructor.

```php
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;

/** @var HandlerProvider $handlerProvider */
$commandBus = new SyncCommandBus($handlerProvider);

$commandBus->dispatch(new CreateProfile($profileId, 'name'));
$commandBus->dispatch(new ChangeProfileName($profileId, 'new name'));
```
### Instant Retry

If you want to retry the command when defined exceptions occur,
you can use the `InstantRetryCommandBus` command bus decorator.

```php
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;

/** @var CommandBus $commandBus */
$commandBus = new InstantRetryCommandBus(
    $commandBus,
    3, // maximum number of retries, default is 3
    [AggregateOutdated::class], // exceptions to retry, default is [AggregateOutdated::class]
);
```
After that, you need to mark the command class with the `#[InstantRetry]` attribute,
to indicate that the command should be retried when the condition is met.

```php
use Patchlevel\EventSourcing\Attribute\InstantRetry;

#[InstantRetry]
final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
```
:::tip
You can override the default values for the maximum number of retries and the conditions
by passing them to the `InstantRetry` attribute.

```php
use Patchlevel\EventSourcing\Attribute\InstantRetry;

#[InstantRetry(3, [AggregateOutdated::class])]
final class CreateProfile
{
}
```
:::

## Provider

There are different types of providers that you can use to register handlers.

### Service Handler Provider

The classic way to handle commands is to use services.
The `ServiceHandlerProvider` is used to handle commands by invoking methods on services.

```php
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;

$provider = new ServiceHandlerProvider([
    new CreateProfileHandler(),
    new ChangeProfileNameHandler(
        new NameValidator(),
    ),
]);
```
### Aggregate Handler Provider

The `AggregateHandlerProvider` is used to handle commands by invoking methods on aggregates.
The special thing about it is that the aggregates themselves are not services,
but the handler provider automatically creates suitable handler services for the aggregates.

```php
use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var RepositoryManager $repositoryManager
 */
$provider = new AggregateHandlerProvider(
    $aggregateRootRegistry,
    $repositoryManager,
);
```
#### Service Locator

If you want service injection in aggregate handler methods,
you need to pass a service locator to the `AggregateHandlerProvider`.
You can use any psr-11 compatible container, or you can use our implementation `ServiceLocator`.

```php
use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\ServiceLocator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

/**
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var RepositoryManager $repositoryManager
 */
$provider = new AggregateHandlerProvider(
    $aggregateRootRegistry,
    $repositoryManager,
    new ServiceLocator([
        'name_validator' => new NameValidator(),
    ]), // or other psr-11 compatible container
);
```
:::tip
You can find suitable implementations of psr-11 containers on [packagist](https://packagist.org/search/?tags=PSR-11).
:::

### Chain Handler Provider

The `ChainHandlerProvider` allows you to combine multiple handler providers.

```php
use Patchlevel\EventSourcing\CommandBus\ChainHandlerProvider;

$provider = new ChainHandlerProvider([
    $serviceHandlerProvider,
    $aggregateHandlerProvider,
]);
```
## Learn more

* [How to use aggregates](aggregate.md)
* [How to use events](events.md)
* [How to use clock](clock.md)
* [How to use aggregate id](identifier.md)
* [How to use query bus](query-bus.md)
* [How to decide across streams with a dynamic consistency boundary](dynamic-consistency-boundary.md)
