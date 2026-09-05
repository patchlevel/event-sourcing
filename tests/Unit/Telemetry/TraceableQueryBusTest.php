<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\Telemetry\TraceableQueryBus;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableQueryBus::class)]
final class TraceableQueryBusTest extends TestCase
{
    use InMemoryTracer;

    public function testDispatchReturnsResult(): void
    {
        $query = new class {
        };

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($query)
            ->willReturn('result');

        $traceableQueryBus = new TraceableQueryBus($queryBus, $this->createTracerProvider());

        self::assertSame('result', $traceableQueryBus->dispatch($query));

        $span = $this->span();

        self::assertSame('event_sourcing.query_bus.dispatch', $span->getName());
        self::assertSame($query::class, $span->getAttributes()->get(TraceAttributes::QUERY_NAME));
    }
}
