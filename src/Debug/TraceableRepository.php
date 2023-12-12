<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\Repository;
use Symfony\Component\Stopwatch\Stopwatch;

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
        private readonly ProfileDataHolder $dataHolder,
        private readonly Stopwatch|null $stopwatch = null,
    ) {
    }

    /** @return T */
    public function load(string $id): AggregateRoot
    {
        $data = ProfileData::loadAggregate($this->aggregateClass, $id);

        $this->dataHolder->addData($data);
        $event = $this->stopwatch?->start('event_sourcing', 'event_sourcing');
        $data->start();

        try {
            return $this->repository->load($id);
        } finally {
            $data->stop();
            $event?->stop();
        }
    }

    public function has(string $id): bool
    {
        $data = ProfileData::hasAggregate($this->aggregateClass, $id);

        $this->dataHolder->addData($data);
        $event = $this->stopwatch?->start('event_sourcing', 'event_sourcing');
        $data->start();

        try {
            return $this->repository->has($id);
        } finally {
            $data->stop();
            $event?->stop();
        }
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $data = ProfileData::saveAggregate($this->aggregateClass, $aggregate->aggregateRootId());

        $this->dataHolder->addData($data);
        $event = $this->stopwatch?->start('event_sourcing', 'event_sourcing');
        $data->start();

        try {
            $this->repository->save($aggregate);
        } finally {
            $data->stop();
            $event?->stop();
        }
    }
}
