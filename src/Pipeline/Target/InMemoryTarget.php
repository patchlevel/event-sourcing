<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;

final class InMemoryTarget implements Target
{
    /** @var list<EventBucket> */
    private array $buckets = [];

    public function save(EventBucket $bucket): void
    {
        $this->buckets[] = $bucket;
    }

    /**
     * @return list<EventBucket>
     */
    public function buckets(): array
    {
        return $this->buckets;
    }
}
