<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;

final class RunSubscriptionEngineRepositoryManager implements RepositoryManager
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly SubscriptionEngine $engine,
        private readonly array|null $ids = null,
        private readonly array|null $groups = null,
        private readonly int|null $limit = null,
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
        return new RunSubscriptionEngineRepository(
            $this->repositoryManager->get($aggregateClass),
            $this->engine,
            $this->ids,
            $this->groups,
            $this->limit,
        );
    }
}
