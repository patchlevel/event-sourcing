<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\Stopwatch\Stopwatch;

final class TraceableRepositoryManager implements RepositoryManager
{
    public function __construct(
        private RepositoryManager $repositoryManager,
        private readonly ProfileDataHolder $dataHolder,
        private readonly Stopwatch|null $stopwatch = null,
    ) {
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return Repository<T>
     *
     * @template T of AggregateRoot
     */
    public function get(string $aggregateClass): Repository
    {
        return new TraceableRepository(
            $this->repositoryManager->get($aggregateClass),
            $aggregateClass,
            $this->dataHolder,
            $this->stopwatch,
        );
    }
}
