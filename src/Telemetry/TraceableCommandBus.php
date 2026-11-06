<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\CommandBus\CommandBus;

final class TraceableCommandBus implements CommandBus
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly CommandBus $commandBus,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function dispatch(object $command): void
    {
        $this->instrumentation->span(
            'event_sourcing.command_bus.dispatch',
            function () use ($command): void {
                $this->commandBus->dispatch($command);
            },
            attributes: [TraceAttributes::COMMAND_NAME => $command::class],
        );
    }
}
