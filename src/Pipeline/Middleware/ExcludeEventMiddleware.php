<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

class ExcludeEventMiddleware implements Middleware
{
    /** @var list<class-string<AggregateChanged>> */
    private array $classes;

    /**
     * @param list<class-string<AggregateChanged>> $classes
     */
    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        foreach ($this->classes as $class) {
            if ($bucket->event() instanceof $class) {
                return [];
            }
        }

        return [$bucket];
    }
}
