<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\DefaultCommandBus;
use Patchlevel\EventSourcing\CommandBus\HandlerDescriptor;
use Patchlevel\EventSourcing\CommandBus\HandlerNotFound;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\CommandBus\DefaultCommandBus */
class DefaultCommandBusTest extends TestCase
{
    use ProphecyTrait;

    public function testHandlerNotFound(): void
    {
        $command = new class {
        };

        $handlerProvider = $this->prophesize(HandlerProvider::class);
        $handlerProvider->handlerForCommand($command::class)->willThrow(new HandlerNotFound($command::class));

        $commandBus = new DefaultCommandBus($handlerProvider->reveal());

        $this->expectException(HandlerNotFound::class);

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
        $handlerProvider->handlerForCommand($command::class)->willReturn(
            new HandlerDescriptor($handler),
        );

        $commandBus = new DefaultCommandBus($handlerProvider->reveal());

        $commandBus->dispatch($command);

        self::assertSame($command, $handler->command);
    }
}
