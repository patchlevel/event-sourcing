<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

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
     * @param callable(EventBucket $event):void|null $observer
     */
    public function run(?callable $observer = null): void
    {
        if ($observer === null) {
            $observer = static function (EventBucket $event): void {
            };
        }

        foreach ($this->source->load() as $bucket) {
            foreach ($this->processMiddlewares($bucket) as $resultBucket) {
                $this->target->save($resultBucket);
            }

            $observer($bucket);
        }
    }

    public function count(): int
    {
        return $this->source->count();
    }

    /**
     * @return list<EventBucket>
     */
    private function processMiddlewares(EventBucket $bucket): array
    {
        $buckets = [$bucket];

        foreach ($this->middlewares as $middleware) {
            $buckets = $this->processMiddleware($middleware, $buckets);
        }

        return $buckets;
    }

    /**
     * @param list<EventBucket> $buckets
     *
     * @return list<EventBucket>
     */
    private function processMiddleware(Middleware $middleware, array $buckets): array
    {
        $result = [];

        foreach ($buckets as $bucket) {
            $result += $middleware($bucket);
        }

        return $result;
    }
}
