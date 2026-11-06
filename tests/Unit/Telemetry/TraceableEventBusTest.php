<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Telemetry\TraceableEventBus;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableEventBus::class)]
final class TraceableEventBusTest extends TestCase
{
    use InMemoryTracer;

    public function testDispatch(): void
    {
        $first = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $second = Message::create(new ProfileVisited(ProfileId::fromString('2')));

        $eventBus = $this->createMock(EventBus::class);
        $eventBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($first, $second);

        $traceableEventBus = new TraceableEventBus($eventBus, $this->createTracerProvider());
        $traceableEventBus->dispatch($first, $second);

        $span = $this->span();

        self::assertSame('event_sourcing.event_bus.dispatch', $span->getName());
        self::assertSame(SpanKind::KIND_PRODUCER, $span->getKind());
        self::assertSame(2, $span->getAttributes()->get(TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT));
    }
}
