<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class TraceableRepository implements Repository
{
    public function __construct(
        /** @var Repository<T> */
        private readonly Repository $repository,
        /** @var class-string<T> */
        private readonly string $aggregateClass,
        private readonly Profiler $profiler,
    ) {
    }

    /** @return T */
    public function load(string $id): AggregateRoot
    {
        return $this->profiler->profile(
            'aggregate.load',
            static fn () => $this->repository->load($id),
            [
                'aggregateClass' => $this->aggregateClass,
                'aggregateId' => $id,
            ]
        );
    }

    public function has(string $id): bool
    {
        return $this->profiler->profile(
            'aggregate.has',
            static fn () => $this->repository->has($id),
            [
                'aggregateClass' => $this->aggregateClass,
                'aggregateId' => $id,
            ]
        );
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $this->profiler->profile(
            'aggregate.save',
            static fn () => $this->repository->save($aggregate),
            [
                'aggregateClass' => $this->aggregateClass,
                'aggregateId' => $aggregate->aggregateRootId(),
            ]
        );
    }
}
