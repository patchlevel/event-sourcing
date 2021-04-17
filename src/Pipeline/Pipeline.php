<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Source\Source;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

class Pipeline
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

    /**
     * @param (callable(EventBucket $event):void)|null $observer
     */
    public function run(?callable $observer = null): void
    {
        if ($observer === null) {
            /** @var callable(EventBucket $event):void $observer */
            $observer = static function (): void {
            };
        }

        foreach ($this->source->load() as $bucket) {
            $observer($bucket);

            $result = ($this->middlewares)($bucket);

            foreach ($result as $resultBucket) {
                $this->target->save($resultBucket);
            }
        }
    }

    public function count(): int
    {
        return $this->source->count();
    }
}
