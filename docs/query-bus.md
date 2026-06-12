# Query Bus

The Query Bus is another optional component in the Event Sourcing library that coordinates the data flow in the system.
Unlike the command bus, the query bus's intention is not to perform actions on the system but instead retrieve information
from the system. It allows you to fully utilize the read write split and the usage of small, independent and tailored
projections.

## Query

First, you need to create a simple data transfer object which will be our query. It represents our intention to retrieve
data from the system.

```php
final class QueryProfile
{
    public function __construct(
        public readonly ProfileId $id,
    ) {
    }
}
```
## Handler

The next step is to create a handler which has a method which can handle the query. The method will be marked with the
`#[Answer]` attribute. The method will be called when the query is dispatched in the bus and should return the desired
data.

```php
use Patchlevel\EventSourcing\Attribute\Answer;

final class QueryProfileHandler
{
    #[Answer]
    public function __invoke(QueryProfile $query): mixed
    {
        return 'result';
    }
}
```
:::warning
A query can only be answered by one method.
:::

:::note
To use Service Handler you need to register the handler in the `ServiceHandlerProvider`.
:::

:::tip
A class can have multiple methods which answer different queries.
:::

### Projector

Another way to handle queries is to answer them directly in the corresponding projectors. The configuration is the same
as when using a dedicated class. The method which should handle the query will be marked with the `#[Answer]` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector('profiles')]
final class ProfileProjector
{
    #[Answer]
    public function answerQueryProfile(QueryProfile $query): mixed
    {
        return 'result';
    }

    // projector related methods to maintain the state of profiles
}
```
:::tip
Using small dedicated projections for each usecase is best practice. Using them directly as query handlers is
endorsed and can reduce fragmentation of the system.
:::

## Setup

We provide a `SyncQueryBus` that you can use to dispatch queries.
You need to pass a `HandlerProvider` to the constructor.

```php
use Patchlevel\EventSourcing\QueryBus\HandlerProvider;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;

/** @var HandlerProvider $handlerProvider */
$queryBus = new SyncQueryBus($handlerProvider);

$result = $queryBus->dispatch(new QueryProfile($profileId));
```
## Provider

We created an interface for `HandlerProvider` which allows you to implement different types of providers that you can
use to register handlers. Right now we have two implementations: `ServiceHandlerProvider` and `ChainHandlerProvider`.

### Service Handler Provider

The `ServiceHandlerProvider` is used to handle queries by invoking methods on services.

```php
use Patchlevel\EventSourcing\QueryBus\ServiceHandlerProvider;

$provider = new ServiceHandlerProvider([
    new QueryProfileHandler(),
    new ProfileProjector(
        $dbalConnection,
    ),
]);
```
### Chain Handler Provider

The `ChainHandlerProvider` allows you to combine multiple handler providers.

```php
use Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider;

$provider = new ChainHandlerProvider([
    $serviceHandlerProvider1,
    $serviceHandlerProvider2,
]);
```
## Learn more

* [How to use aggregates](aggregate.md)
* [How to use events](events.md)
* [How to use subscriptions](subscription.md)
* [How to use command bus](command-bus.md)
