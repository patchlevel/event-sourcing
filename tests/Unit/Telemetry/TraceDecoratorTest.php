<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TraceHeader;
use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use Patchlevel\EventSourcing\Telemetry\TraceDecorator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[CoversClass(TraceDecorator::class)]
final class TraceDecoratorTest extends TestCase
{
    use InMemoryTracer;

    public function testAddsTraceHeaderInsideSpan(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());
        $decorator = new TraceDecorator();

        $message = $instrumentation->span(
            'test.span',
            fn (): Message => $decorator(Message::create($this->event())),
        );

        self::assertTrue($message->hasHeader(TraceHeader::class));

        $span = $this->span();
        $traceparent = $message->header(TraceHeader::class)->traceparent;

        self::assertTrue(str_contains($traceparent, $span->getContext()->getTraceId()));
        self::assertTrue(str_contains($traceparent, $span->getContext()->getSpanId()));
    }

    public function testNoTraceHeaderWithoutActiveSpan(): void
    {
        $decorator = new TraceDecorator();

        $message = $decorator(Message::create($this->event()));

        self::assertFalse($message->hasHeader(TraceHeader::class));
    }

    public function testKeepsExistingTraceHeader(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());
        $decorator = new TraceDecorator();

        $message = $instrumentation->span(
            'test.span',
            fn (): Message => $decorator(
                Message::create($this->event())->withHeader(new TraceHeader('explicit')),
            ),
        );

        self::assertSame('explicit', $message->header(TraceHeader::class)->traceparent);
    }

    private function event(): ProfileVisited
    {
        return new ProfileVisited(ProfileId::fromString('1'));
    }
}
