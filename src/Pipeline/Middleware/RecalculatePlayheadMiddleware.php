<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
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
     * @return list<AggregateChanged>
     */
    public function __invoke(AggregateChanged $aggregateChanged): array
    {
        $playhead = $this->nextPlayhead($aggregateChanged->aggregateId());

        if ($aggregateChanged->playhead() === $playhead) {
            return [$aggregateChanged];
        }

        $this->reflectionProperty->setValue($aggregateChanged, $playhead);

        return [$aggregateChanged];
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
