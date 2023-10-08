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
        $data = ProfileData::load($this->aggregateClass, $id);

        $this->dataHolder->addData($data);
        $event = $this->stopwatch?->start('event_sourcing', 'event_sourcing');
        $data->start();

        try {
            $aggregate = $this->repository->load($id);
        } finally {
            $data->stop();
            $event?->stop();
        }

        return $aggregate;
    }

    public function has(string $id): bool
    {
        return $this->repository->has($id);
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $event = $this->stopwatch?->start('event_sourcing', 'event_sourcing');

        $this->repository->save($aggregate);

        $event?->stop();
    }
}
