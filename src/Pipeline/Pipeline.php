<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Closure;
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Source\Source;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

final class Pipeline
{
    private ChainMiddleware $middlewares;

    /** @param list<Middleware> $middlewares */
    public function __construct(
        private readonly Source $source,
        private readonly Target $target,
        array $middlewares = [],
    ) {
        $this->middlewares = new ChainMiddleware($middlewares);
    }

    public function run(Closure|null $observer = null): void
    {
        foreach ($this->source->load() as $message) {
            $result = ($this->middlewares)($message);
            $this->target->save(...$result);

            if (!$observer) {
                continue;
            }

            $observer($message);
        }
    }

    public function count(): int
    {
        return $this->source->count();
    }
}
