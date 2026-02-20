<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use function class_implements;
use function class_parents;

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
     * @return iterable<int, HandlerDescriptor>
     */
    public function handlerForCommand(string $commandClass): iterable
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        foreach (self::resolveClasses($commandClass) as $class) {
            yield from $this->handlers[$class] ?? [];
        }
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

    /**
     * @param class-string $class
     *
     * @return array<class-string, class-string>
     */
    private static function resolveClasses(string $class): array
    {
        /** @var array<class-string, class-string> $classes */
        $classes = [$class => $class]
            + class_parents($class)
            + class_implements($class);

        return $classes;
    }
}
