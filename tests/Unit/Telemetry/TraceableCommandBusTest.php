<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\Telemetry\TraceableCommandBus;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableCommandBus::class)]
final class TraceableCommandBusTest extends TestCase
{
    use InMemoryTracer;

    public function testDispatch(): void
    {
        $command = new class {
        };

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command);

        $traceableCommandBus = new TraceableCommandBus($commandBus, $this->createTracerProvider());
        $traceableCommandBus->dispatch($command);

        $span = $this->span();

        self::assertSame('event_sourcing.command_bus.dispatch', $span->getName());
        self::assertSame($command::class, $span->getAttributes()->get(TraceAttributes::COMMAND_NAME));
    }
}
