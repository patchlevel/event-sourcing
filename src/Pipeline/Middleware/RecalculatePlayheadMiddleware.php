<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;

class RecalculatePlayheadMiddleware implements Middleware
{
    /** @var array<string, int> */
    private array $index = [];

    private ReflectionProperty $reflectionProperty;

    public function __construct()
    {
        $reflectionClass = new ReflectionClass(AggregateChanged::class);

        $this->reflectionProperty = $reflectionClass->getProperty('playhead');
        $this->reflectionProperty->setAccessible(true);
    }

    /**
     * @return list<EventBucket>
     */
    public function __invoke(EventBucket $bucket): array
    {
        $event = $bucket->event();
        $playhead = $this->nextPlayhead($event->aggregateId());

        if ($event->playhead() === $playhead) {
            return [$bucket];
        }

        $this->reflectionProperty->setValue($event, $playhead);

        return [$bucket];
    }

    private function nextPlayhead(string $aggregateId): int
    {
        if (!array_key_exists($aggregateId, $this->index)) {
            $this->index[$aggregateId] = -1;
        }

        $this->index[$aggregateId]++;

        return $this->index[$aggregateId];
    }
}
