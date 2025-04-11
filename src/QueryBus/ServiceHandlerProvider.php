<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

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
     * @param class-string $queryClass
     *
     * @return iterable<int, HandlerDescriptor>
     */
    public function handlerForQuery(string $queryClass): iterable
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->handlers[$queryClass] ?? [];
    }

    private function initialize(): void
    {
        foreach ($this->services as $service) {
            foreach (HandlerFinder::findInClass($service::class) as $handler) {
                if ($handler->static) {
                    $this->handlers[$handler->queryClass][] = new HandlerDescriptor(
                        $service::{$handler->method}(...),
                    );

                    continue;
                }

                $this->handlers[$handler->queryClass][] = new HandlerDescriptor(
                    $service->{$handler->method}(...),
                );
            }
        }

        $this->initialized = true;
    }
}
