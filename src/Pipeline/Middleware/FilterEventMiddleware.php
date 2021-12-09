<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

final class FilterEventMiddleware implements Middleware
{
    /** @var callable(AggregateChanged $event):bool */
    private $callable;

    /**
     * @param callable(AggregateChanged $event):bool $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        $result = ($this->callable)($bucket->event());

        if ($result) {
            return [$bucket];
        }

        return [];
    }
}
