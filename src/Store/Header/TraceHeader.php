<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/**
 * W3C trace context of the trace in which the message was recorded.
 *
 * Deliberately free of any OpenTelemetry types, so that messages carrying this
 * header can still be deserialized without the open-telemetry packages installed.
 *
 * @immutable
 */
final class TraceHeader
{
    public function __construct(
        public readonly string $traceparent,
        public readonly string|null $tracestate = null,
    ) {
    }
}
