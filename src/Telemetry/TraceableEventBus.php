<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;

use function count;

final class TraceableEventBus implements EventBus
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly EventBus $eventBus,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function dispatch(Message ...$messages): void
    {
        $this->instrumentation->span(
            'event_sourcing.event_bus.dispatch',
            function () use ($messages): void {
                $this->eventBus->dispatch(...$messages);
            },
            SpanKind::KIND_PRODUCER,
            [
                TraceAttributes::MESSAGING_SYSTEM => TraceAttributes::SYSTEM,
                TraceAttributes::MESSAGING_OPERATION_NAME => 'dispatch',
                TraceAttributes::MESSAGING_OPERATION_TYPE => TraceAttributes::OPERATION_TYPE_SEND,
                TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT => count($messages),
            ],
        );
    }
}
