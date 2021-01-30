<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Source\Source;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

class Pipeline
{
    private Source $source;
    private Target $target;
    /** @var list<Middleware> */
    private array $middlewares;

    /**
     * @param list<Middleware> $middlewares
     */
    public function __construct(Source $source, Target $target, array $middlewares = [])
    {
        $this->source = $source;
        $this->target = $target;
        $this->middlewares = $middlewares;
    }

    /**
     * @param callable(AggregateChanged $event):void|null $observer
     */
    public function run(?callable $observer = null): void
    {
        if ($observer === null) {
            $observer = static function (AggregateChanged $event): void {
            };
        }

        foreach ($this->source->load() as $event) {
            foreach ($this->processMiddlewares($event) as $resultEvent) {
                $this->target->save($resultEvent);
            }

            $observer($event);
        }
    }

    public function count(): int
    {
        return $this->source->count();
    }

    /**
     * @return list<AggregateChanged>
     */
    private function processMiddlewares(AggregateChanged $event): array
    {
        $events = [$event];

        foreach ($this->middlewares as $middleware) {
            $events = $this->processMiddleware($middleware, $events);
        }

        return $events;
    }

    /**
     * @param list<AggregateChanged> $events
     *
     * @return list<AggregateChanged>
     */
    private function processMiddleware(Middleware $middleware, array $events): array
    {
        $result = [];

        foreach ($events as $event) {
            $result += $middleware($event);
        }

        return $result;
    }
}
