<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function microtime;

final class ProfileData
{
    private float|null $start = null;
    private float|null $duration = null;

    private function __construct(
        /** @var 'load'|'has'|'save' */
        private readonly string $type,
        /** @var class-string<AggregateRoot> */
        private readonly string $aggregateClass,
        private readonly string $aggregateId,
    ) {
    }

    public function start(): void
    {
        $this->start = microtime(true);
    }

    public function stop(): void
    {
        if ($this->start === null) {
            return;
        }

        $this->duration = microtime(true) - $this->start;
    }

    /** @return 'load'|'has'|'save' */
    public function type(): string
    {
        return $this->type;
    }

    /** @return class-string<AggregateRoot> */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function duration(): float|null
    {
        return $this->duration;
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    public static function loadAggregate(string $aggregateClass, string $aggregateId): self
    {
        return new ProfileData('load', $aggregateClass, $aggregateId);
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    public static function hasAggregate(string $aggregateClass, string $aggregateId): self
    {
        return new ProfileData('has', $aggregateClass, $aggregateId);
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    public static function saveAggregate(string $aggregateClass, string $aggregateId): self
    {
        return new ProfileData('save', $aggregateClass, $aggregateId);
    }
}
