<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use ArrayObject;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

use function array_values;
use function iterator_to_array;

trait InMemoryTracer
{
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $spanStorage;

    private TracerProvider $tracerProvider;

    private function createTracerProvider(): TracerProvider
    {
        /** @var ArrayObject<int, ImmutableSpan> $storage */
        $storage = new ArrayObject();

        $this->spanStorage = $storage;
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(new InMemoryExporter($storage)),
        );

        return $this->tracerProvider;
    }

    /**
     * Buffers spans until they are flushed, so that the effect of the
     * ForceFlushListener becomes observable.
     */
    private function createBatchingTracerProvider(): TracerProvider
    {
        /** @var ArrayObject<int, ImmutableSpan> $storage */
        $storage = new ArrayObject();

        $this->spanStorage = $storage;
        $this->tracerProvider = new TracerProvider(
            new BatchSpanProcessor(
                new InMemoryExporter($storage),
                Clock::getDefault(),
                scheduledDelayMillis: 60_000,
                autoFlush: false,
            ),
        );

        return $this->tracerProvider;
    }

    /** @return list<ImmutableSpan> */
    private function spans(): array
    {
        return array_values(iterator_to_array($this->spanStorage));
    }

    private function span(int $index = 0): ImmutableSpan
    {
        return $this->spans()[$index];
    }
}
