<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use Patchlevel\EventSourcing\Telemetry\SpanEventLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(SpanEventLogger::class)]
final class SpanEventLoggerTest extends TestCase
{
    use InMemoryTracer;

    public function testAddsSpanEvent(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());
        $logger = new SpanEventLogger();

        $instrumentation->span(
            'test.span',
            static function () use ($logger): void {
                $logger->debug('Repository: aggregate saved.');
            },
        );

        $events = $this->span()->getEvents();

        self::assertCount(1, $events);
        self::assertSame('Repository: aggregate saved.', $events[0]->getName());
        self::assertSame(LogLevel::DEBUG, $events[0]->getAttributes()->get('log.level'));
    }

    public function testDelegatesToInnerLogger(): void
    {
        $this->createTracerProvider();

        $inner = $this->createMock(LoggerInterface::class);
        $inner
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, 'message', ['foo' => 'bar']);

        $logger = new SpanEventLogger($inner);
        $logger->info('message', ['foo' => 'bar']);
    }

    public function testNoSpanEventWithoutActiveSpan(): void
    {
        $this->createTracerProvider();

        $logger = new SpanEventLogger();
        $logger->error('message');

        self::assertCount(0, $this->spans());
    }
}
