<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

final class ServiceHandlerProvider implements HandlerProvider
{
    private bool $initialized = false;

    /** @var array<class-string, list<HandlerDescriptor>> */
    private array $handlers = [];

    /** @param iterable<object> $services */
    public function __construct(
        private readonly iterable $services,
    ) {
    }

    /**
     * @param class-string $commandClass
     *
     * @return iterable<HandlerDescriptor>
     */
    public function handlerForCommand(string $commandClass): iterable
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->handlers[$commandClass] ?? [];
    }

    private function initialize(): void
    {
        foreach ($this->services as $service) {
            foreach (HandlerFinder::findInClass($service::class) as $handler) {
                if ($handler->static) {
                    $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                        $service::{$handler->method}(...),
                    );

                    continue;
                }

                $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                    $service->{$handler->method}(...),
                );
            }
        }

        $this->initialized = true;
    }
}
