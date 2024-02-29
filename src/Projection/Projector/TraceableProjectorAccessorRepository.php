<?php

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\Trace;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;

final class TraceableProjectorAccessorRepository implements ProjectorAccessorRepository
{
    private bool $init = false;

    /**
     * @var array<string, TraceableProjectorAccessor>
     */
    private array $projectorsMap = [];

    public function __construct(
        private readonly ProjectorAccessorRepository $parent,
        private readonly TraceStack $traceStack,
    ) {
    }

    /**
     * @return iterable<ProjectorAccessor>
     */
    public function all(): iterable
    {
        if ($this->init === false) {
            $this->init();
        }

        return array_values($this->projectorsMap);
    }

    public function get(string $id): ProjectorAccessor|null
    {
        if ($this->init === false) {
            $this->init();
        }

        return $this->projectorsMap[$id] ?? null;
    }

    private function init(): void
    {
        $this->init = true;

        foreach ($this->parent->all() as $projectorAccessor) {
            $this->projectorsMap[$projectorAccessor->id()] = new TraceableProjectorAccessor(
                $projectorAccessor,
                $this->wrapper(...)
            );
        }
    }

    public function wrapper($projectorAccessor, Closure $closure): Closure
    {
        return function (Message $message) use ($projectorAccessor, $closure) {
            $trace = new Trace(
                $projectorAccessor->id(),
                'event_sourcing:' . $projectorAccessor->group()
            );

            $this->traceStack->add($trace);
            try {
                return $closure($message);
            } finally {
                $this->traceStack->remove($trace);
            }
        };
    }
}