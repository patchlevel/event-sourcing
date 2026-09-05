<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\NoopTracerProvider;
use Patchlevel\EventSourcing\Telemetry\ForceFlushListener;
use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForceFlushListener::class)]
final class ForceFlushListenerTest extends TestCase
{
    use InMemoryTracer;

    public function testFlushesBatchedSpans(): void
    {
        $tracerProvider = $this->createBatchingTracerProvider();
        $instrumentation = new Instrumentation($tracerProvider);

        $instrumentation->span('test.span', static fn (): null => null);

        // with a batching processor the span is still buffered
        self::assertCount(0, $this->spans());

        $listener = new ForceFlushListener($tracerProvider);
        $listener(new WorkerRunningEvent($this->createMock(Worker::class)));

        self::assertCount(1, $this->spans());
    }

    public function testDoesNothingWithoutSdk(): void
    {
        $this->createTracerProvider();

        $listener = new ForceFlushListener(new NoopTracerProvider());
        $listener(new WorkerRunningEvent($this->createMock(Worker::class)));

        self::assertCount(0, $this->spans());
    }
}
