<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

use function array_key_exists;

final class TraceableRepositoryManager implements RepositoryManager
{
    /** @var array<class-string<AggregateRoot>, Repository> */
    private array $instances = [];

    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly TracerProviderInterface|null $tracerProvider = null,
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
        if (array_key_exists($aggregateClass, $this->instances)) {
            /** @var Repository<T> $repository */
            $repository = $this->instances[$aggregateClass];

            return $repository;
        }

        return $this->instances[$aggregateClass] = new TraceableRepository(
            $this->repositoryManager->get($aggregateClass),
            $this->aggregateRootRegistry->aggregateName($aggregateClass),
            $this->tracerProvider,
        );
    }
}
