<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;

class FromIndexEventMiddleware implements Middleware
{
    private int $fromIndex;

    public function __construct(int $fromIndex)
    {
        $this->fromIndex = $fromIndex;
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        if ($bucket->index() > $this->fromIndex) {
            return [$bucket];
        }

        return [];
    }
}
