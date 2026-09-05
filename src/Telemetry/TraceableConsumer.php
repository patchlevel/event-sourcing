<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\Message\Message;

final class TraceableConsumer implements Consumer
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly Consumer $consumer,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function consume(Message $message): void
    {
        $this->instrumentation->span(
            'event_sourcing.event_bus.consume',
            function () use ($message): void {
                $this->consumer->consume($message);
            },
            SpanKind::KIND_CONSUMER,
            [
                TraceAttributes::MESSAGING_SYSTEM => TraceAttributes::SYSTEM,
                TraceAttributes::MESSAGING_OPERATION_NAME => 'consume',
                TraceAttributes::MESSAGING_OPERATION_TYPE => TraceAttributes::OPERATION_TYPE_PROCESS,
                ...MessageAttributes::from($message),
            ],
        );
    }
}
