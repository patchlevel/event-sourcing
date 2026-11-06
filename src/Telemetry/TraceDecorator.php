<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Store\Header\TraceHeader;

use function is_array;
use function is_string;

/**
 * Persists the current W3C trace context on every recorded message, so that
 * asynchronous processing can be linked back to the trace which recorded it.
 */
final class TraceDecorator implements MessageDecorator
{
    private readonly TextMapPropagatorInterface $propagator;

    public function __construct(TextMapPropagatorInterface|null $propagator = null)
    {
        $this->propagator = $propagator ?? TraceContextPropagator::getInstance();
    }

    public function __invoke(Message $message): Message
    {
        if ($message->hasHeader(TraceHeader::class)) {
            return $message;
        }

        $carrier = [];
        $this->propagator->inject($carrier);

        if (!is_array($carrier)) {
            return $message;
        }

        $traceparent = $carrier[TraceContextPropagator::TRACEPARENT] ?? null;

        if (!is_string($traceparent)) {
            return $message;
        }

        $tracestate = $carrier[TraceContextPropagator::TRACESTATE] ?? null;

        return $message->withHeader(
            new TraceHeader($traceparent, is_string($tracestate) ? $tracestate : null),
        );
    }
}
