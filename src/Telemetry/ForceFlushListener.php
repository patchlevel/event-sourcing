<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProviderInterface;
use Patchlevel\Worker\Event\WorkerRunningEvent;

/**
 * Flushes finished spans after every worker cycle.
 *
 * The subscription worker is a long running process. With a batching span processor
 * the spans would otherwise stay in memory until the process shuts down, and would be
 * lost completely if the process is killed.
 *
 * Without an OpenTelemetry SDK there is nothing to flush and this listener does nothing.
 */
final class ForceFlushListener
{
    public function __construct(
        private readonly TracerProviderInterface|null $tracerProvider = null,
    ) {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        $tracerProvider = $this->tracerProvider ?? Globals::tracerProvider();

        if (!$tracerProvider instanceof SdkTracerProviderInterface) {
            return;
        }

        $tracerProvider->forceFlush();
    }
}
