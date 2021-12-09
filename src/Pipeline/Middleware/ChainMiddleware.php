<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;

final class ChainMiddleware implements Middleware
{
    /** @var list<Middleware> */
    private array $middlewares;

    /**
     * @param list<Middleware> $middlewares
     */
    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
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
