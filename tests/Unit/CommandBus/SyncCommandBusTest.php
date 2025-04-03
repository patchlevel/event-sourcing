<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\HandlerDescriptor;
use Patchlevel\EventSourcing\CommandBus\HandlerNotFound;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\CommandBus\MultipleHandlersFound;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(SyncCommandBus::class)]
final class SyncCommandBusTest extends TestCase
{
    use ProphecyTrait;

    public function testHandlerNotFound(): void
    {
        $command = new class {
        };

        $handlerProvider = $this->prophesize(HandlerProvider::class);
        $handlerProvider->handlerForCommand($command::class)->willReturn([]);

        $commandBus = new SyncCommandBus($handlerProvider->reveal());

        $this->expectException(HandlerNotFound::class);

        $commandBus->dispatch($command);
    }

    public function testMultipleHandlersFound(): void
    {
        $command = new class {
        };

        $handlerProvider = $this->prophesize(HandlerProvider::class);
        $handlerProvider->handlerForCommand($command::class)->willReturn([
            new HandlerDescriptor(static fn () => null),
            new HandlerDescriptor(static fn () => null),
        ]);

        $commandBus = new SyncCommandBus($handlerProvider->reveal());

        $this->expectException(MultipleHandlersFound::class);

        $commandBus->dispatch($command);
    }

    public function testHandleSuccess(): void
    {
        $command = new class {
        };

        $handler = new class {
            public object|null $command = null;

            public function __invoke(object $command): void
            {
                $this->command = $command;
            }
        };

        $handlerProvider = $this->prophesize(HandlerProvider::class);
        $handlerProvider->handlerForCommand($command::class)->willReturn([
            new HandlerDescriptor($handler),
        ]);

        $commandBus = new SyncCommandBus($handlerProvider->reveal());

        $commandBus->dispatch($command);

        self::assertSame($command, $handler->command);
    }
}
