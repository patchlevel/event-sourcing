<?php

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

final class TraceableProjectorAccessor implements ProjectorAccessor
{
    public function __construct(
        private readonly ProjectorAccessor $parent,
        private readonly Closure $wrapper
    ) {
    }

    public function id(): string
    {
        return $this->parent->id();
    }

    public function group(): string
    {
        return $this->parent->group();
    }

    public function runMode(): RunMode
    {
        return $this->parent->runMode();
    }

    public function setupMethod(): Closure|null
    {
        return $this->parent->setupMethod();
    }

    public function teardownMethod(): Closure|null
    {
        return $this->parent->teardownMethod();
    }

    /**
     * @param class-string $eventClass
     *
     * @return iterable<Closure>
     */
    public function subscribeMethods(string $eventClass): iterable
    {
        return array_map(
            fn(Closure $closure) => ($this->wrapper)($this, $closure),
            $this->parent->subscribeMethods($eventClass)
        );
    }
}