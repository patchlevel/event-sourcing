<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function uniqid;

#[CoversClass(Instrumentation::class)]
final class InstrumentationTest extends TestCase
{
    use InMemoryTracer;

    public function testSpanWithResult(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());

        $expected = uniqid('result-');

        $result = $instrumentation->span(
            'test.span',
            static fn (): string => $expected,
            SpanKind::KIND_PRODUCER,
            ['foo' => 'bar'],
        );

        self::assertSame($expected, $result);
        self::assertCount(1, $this->spans());

        $span = $this->span();

        self::assertSame('test.span', $span->getName());
        self::assertSame(SpanKind::KIND_PRODUCER, $span->getKind());
        self::assertSame('bar', $span->getAttributes()->get('foo'));
        self::assertSame(StatusCode::STATUS_UNSET, $span->getStatus()->getCode());
    }

    public function testNullAttributesAreRemoved(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());

        $instrumentation->span(
            'test.span',
            static fn (): null => null,
            attributes: ['foo' => null, 'bar' => 'baz'],
        );

        $attributes = $this->span()->getAttributes();

        self::assertFalse($attributes->has('foo'));
        self::assertSame('baz', $attributes->get('bar'));
    }

    public function testSpanRecordsException(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());

        $this->expectException(RuntimeException::class);

        try {
            $instrumentation->span(
                'test.span',
                static fn (): never => throw new RuntimeException('ERROR'),
            );
        } finally {
            $span = $this->span();

            self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
            self::assertSame('ERROR', $span->getStatus()->getDescription());
            self::assertCount(1, $span->getEvents());
            self::assertSame('exception', $span->getEvents()[0]->getName());
        }
    }

    public function testNestedSpansAreLinkedByParent(): void
    {
        $instrumentation = new Instrumentation($this->createTracerProvider());

        $instrumentation->span(
            'outer',
            static function () use ($instrumentation): void {
                $instrumentation->span('inner', static fn (): null => null);
            },
        );

        [$inner, $outer] = $this->spans();

        self::assertSame('inner', $inner->getName());
        self::assertSame('outer', $outer->getName());
        self::assertSame($outer->getContext()->getSpanId(), $inner->getParentContext()->getSpanId());
    }
}
