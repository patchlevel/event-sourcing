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
    private Source $source;
    private Target $target;
    private ChainMiddleware $middlewares;

    /**
     * @param list<Middleware> $middlewares
     */
    public function __construct(Source $source, Target $target, array $middlewares = [])
    {
        $this->source = $source;
        $this->target = $target;
        $this->middlewares = new ChainMiddleware($middlewares);
    }

    public function run(?Closure $observer = null): void
    {
        foreach ($this->source->load() as $message) {
            $result = ($this->middlewares)($message);

            foreach ($result as $resultMessage) {
                $this->target->save($resultMessage);
            }

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
