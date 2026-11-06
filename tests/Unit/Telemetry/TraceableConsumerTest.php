<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Telemetry\TraceableConsumer;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableConsumer::class)]
final class TraceableConsumerTest extends TestCase
{
    use InMemoryTracer;

    public function testConsume(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'));

        $consumer = $this->createMock(Consumer::class);
        $consumer
            ->expects($this->once())
            ->method('consume')
            ->with($message);

        $traceableConsumer = new TraceableConsumer($consumer, $this->createTracerProvider());
        $traceableConsumer->consume($message);

        $span = $this->span();
        $attributes = $span->getAttributes();

        self::assertSame('event_sourcing.event_bus.consume', $span->getName());
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSame(ProfileVisited::class, $attributes->get(TraceAttributes::EVENT_NAME));
        self::assertSame('event-1', $attributes->get(TraceAttributes::MESSAGING_MESSAGE_ID));
        self::assertSame(
            'correlation-1',
            $attributes->get(TraceAttributes::MESSAGING_MESSAGE_CONVERSATION_ID),
        );
        self::assertFalse($attributes->has(TraceAttributes::CAUSATION_ID));
    }
}
