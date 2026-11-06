<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\QueryBus\QueryBus;

final class TraceableQueryBus implements QueryBus
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly QueryBus $queryBus,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function dispatch(object $query): mixed
    {
        return $this->instrumentation->span(
            'event_sourcing.query_bus.dispatch',
            fn (): mixed => $this->queryBus->dispatch($query),
            attributes: [TraceAttributes::QUERY_NAME => $query::class],
        );
    }
}
