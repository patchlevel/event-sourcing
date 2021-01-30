<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;

interface Middleware
{
    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array;
}
