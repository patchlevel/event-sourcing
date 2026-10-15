<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Closure;
use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use ReflectionMethod;

use function class_implements;
use function class_parents;

final class ServiceHandlerProvider implements HandlerProvider
{
    private bool $initialized = false;

    /** @var array<class-string, list<HandlerDescriptor>> */
    private array $handlers = [];

    /**
     * Without a parameter resolver, handlers are called with the command only.
     *
     * @param iterable<object> $services
     */
    public function __construct(
        private readonly iterable $services,
        private readonly ParameterResolver|null $parameterResolver = null,
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
                $callable = $handler->static
                    ? $service::{$handler->method}(...)
                    : $service->{$handler->method}(...);

                $this->handlers[$handler->commandClass][] = $this->descriptor(
                    $callable,
                    new ReflectionMethod($service, $handler->method),
                );
            }
        }

        $this->initialized = true;
    }

    private function descriptor(Closure $callable, ReflectionMethod $method): HandlerDescriptor
    {
        $parameterResolver = $this->parameterResolver;

        if ($parameterResolver === null || $method->getNumberOfParameters() <= 1) {
            return new HandlerDescriptor($callable);
        }

        return new HandlerDescriptor(
            static fn (object $command): mixed => $callable(...$parameterResolver->resolve($method, $command)),
            $method->getDeclaringClass()->getName() . '::' . $method->getName(),
        );
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
