# OpenTelemetry

With [OpenTelemetry](https://opentelemetry.io/) you can follow a command through your whole system: which aggregate was
saved, which store query it triggered, which subscription processed the resulting event and which projection was
written. The library ships wrappers for all of its entry points which create spans, plus a header that carries the trace
context into asynchronous processing.

## Installation

The OpenTelemetry packages are optional. Install the api package to use the wrappers, and an sdk to actually export
spans.

```bash
composer require open-telemetry/api
composer require open-telemetry/sdk
```
:::note
Without a configured sdk the api falls back to a noop implementation. The wrappers are then still safe to wire, they
just don't record anything.
:::

## Wrap the services

Every wrapper takes the service it decorates and an optional `TracerProviderInterface`. Without one, the globally
configured provider is used.

```php
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Telemetry\TraceableRepositoryManager;
use Patchlevel\EventSourcing\Telemetry\TraceableStore;

/**
 * @var RepositoryManager $repositoryManager
 * @var AggregateRootRegistry $aggregateRootRegistry
 * @var Store $store
 * @var TracerProviderInterface $tracerProvider
 */
$repositoryManager = new TraceableRepositoryManager(
    $repositoryManager,
    $aggregateRootRegistry,
    $tracerProvider,
);

$store = new TraceableStore($store, $tracerProvider);
```
These wrappers are available:

* `TraceableRepositoryManager` and `TraceableRepository` for loading and saving aggregates.
* `TraceableStore` for store operations.
* `TraceableEventBus` and `TraceableConsumer` for the [event bus](event-bus.md).
* `TraceableCommandBus` and `TraceableQueryBus` for the [command bus](command-bus.md) and [query bus](query-bus.md).
* `TraceableSubscriptionEngine` for the engine commands like boot and run.

:::warning
The `TraceableStore` implements `Store` and `AppendStore` only. Pass the concrete store to the schema director and to
the console commands, they need `DoctrineSchemaConfigurator`, `ProvideDbalConnection` and `SubscriptionStore`, which
the wrapper does not forward. Wrap the store only where it is injected into repositories and buses.
:::

## Trace asynchronous processing

A subscription processes an event long after it was recorded, often in a different process. To connect both, the trace
context of the recording is stored on the message with the `TraceDecorator`, a [message decorator](message-decorator.md).

```php
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\CorrelationCausationDecorator;
use Patchlevel\EventSourcing\Telemetry\TraceDecorator;

/** @var MessageContext $messageContext */
$decorator = new ChainMessageDecorator([
    new CorrelationCausationDecorator($messageContext),
    new TraceDecorator(),
]);
```
:::tip
Combine it with [correlation and causation](correlation-causation.md) ids. The ids describe the business transaction,
the trace describes the technical execution.
:::

On the reading side, the `TracingSubscriber` creates one span per message and subscription. Register it on the event
dispatcher of the [subscription engine](subscription.md).

```php
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Telemetry\TracingSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @var MessageLoader $messageLoader
 * @var SubscriptionStore $subscriptionStore
 * @var SubscriberAccessorRepository $subscriberRepository
 * @var TracerProviderInterface $tracerProvider
 */
$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber(new TracingSubscriber($tracerProvider));

$engine = new DefaultSubscriptionEngine(
    $messageLoader,
    $subscriptionStore,
    $subscriberRepository,
    eventDispatcher: $eventDispatcher,
);
```
:::note
The processing span does not become a child of the recording span, it references it as a span link. One event is
processed by many subscriptions at arbitrary points in time, so a parent child relation would be wrong. This follows the
OpenTelemetry messaging conventions.
:::

## Flush spans in long running workers

The subscription worker runs for hours. With a batching span processor the spans stay in memory until the process ends,
so nothing shows up in your backend, and everything is lost if the process is killed. The `ForceFlushListener` flushes
after every worker cycle.

```php
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Telemetry\ForceFlushListener;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** @var TracerProviderInterface $tracerProvider */
$workerEventDispatcher = new EventDispatcher();
$workerEventDispatcher->addListener(
    WorkerRunningEvent::class,
    new ForceFlushListener($tracerProvider),
);
```
:::danger
Without flushing you will see no traces at all from the subscription worker. This is the most common cause of missing
spans.
:::

## Add the log output to your spans

The library logs at all of its seams. The `SpanEventLogger` writes every log record as an event onto the active span, so
you also see what happened inside parts which have no dedicated span.

```php
use Patchlevel\EventSourcing\Telemetry\SpanEventLogger;
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger */
$logger = new SpanEventLogger($logger);
```
## Span reference

| Span | Kind |
|---|---|
| `event_sourcing.repository.load` / `.has` / `.save` | internal |
| `event_sourcing.store.load` / `.count` / `.streams` / `.remove` / `.archive` / `.transactional` | internal |
| `event_sourcing.store.save` | producer |
| `event_sourcing.event_bus.dispatch` | producer |
| `event_sourcing.event_bus.consume` | consumer |
| `event_sourcing.command_bus.dispatch` / `event_sourcing.query_bus.dispatch` | internal |
| `event_sourcing.subscription.run` (and the other engine commands) | internal |
| `event_sourcing.subscription.process` | consumer |

Where the OpenTelemetry messaging conventions define an attribute, it is used: `messaging.message.id` is the event id,
`messaging.message.conversation_id` is the correlation id and `messaging.destination.name` is the stream name.
Everything else uses the `event_sourcing.` prefix, for example `event_sourcing.aggregate.name`,
`event_sourcing.causation_id` and `event_sourcing.subscription.id`.

:::warning
Event payloads are never attached to spans, because they regularly contain personal data. If you add your own
attributes, keep [personal data](personal-data.md) out of them.
:::

## Learn more

* [How to track correlation and causation](correlation-causation.md)
* [How to decorate messages](message-decorator.md)
* [How to process events with subscriptions](subscription.md)
* [How to protect personal data](personal-data.md)
