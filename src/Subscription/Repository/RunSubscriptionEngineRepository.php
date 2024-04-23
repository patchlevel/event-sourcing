<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class RunSubscriptionEngineRepository implements Repository
{
    /**
     * @param Repository<T>     $repository
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     */
    public function __construct(
        private readonly Repository $repository,
        private readonly SubscriptionEngine $engine,
        private readonly array|null $ids = null,
        private readonly array|null $groups = null,
        private readonly int|null $limit = null,
    ) {
    }

    /** @return T */
    public function load(AggregateRootId $id): AggregateRoot
    {
        return $this->repository->load($id);
    }

    public function has(AggregateRootId $id): bool
    {
        return $this->repository->has($id);
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $this->repository->save($aggregate);

        try {
            $this->engine->run(
                new SubscriptionEngineCriteria(
                    $this->ids,
                    $this->groups,
                ),
                $this->limit,
            );
        } catch (AlreadyProcessing) {
            // do nothing
        }
    }
}
