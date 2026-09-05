<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Repository\Repository;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class TraceableRepository implements Repository
{
    private readonly Instrumentation $instrumentation;

    /** @param Repository<T> $repository */
    public function __construct(
        private readonly Repository $repository,
        private readonly string $aggregateName,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    /** @return T */
    public function load(Identifier $id): AggregateRoot
    {
        return $this->instrumentation->span(
            'event_sourcing.repository.load',
            fn (): AggregateRoot => $this->repository->load($id),
            attributes: [
                TraceAttributes::AGGREGATE_NAME => $this->aggregateName,
                TraceAttributes::AGGREGATE_ID => $id->toString(),
            ],
        );
    }

    public function has(Identifier $id): bool
    {
        return $this->instrumentation->span(
            'event_sourcing.repository.has',
            fn (): bool => $this->repository->has($id),
            attributes: [
                TraceAttributes::AGGREGATE_NAME => $this->aggregateName,
                TraceAttributes::AGGREGATE_ID => $id->toString(),
            ],
        );
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $this->instrumentation->span(
            'event_sourcing.repository.save',
            function () use ($aggregate): void {
                $this->repository->save($aggregate);
            },
            attributes: [
                TraceAttributes::AGGREGATE_NAME => $this->aggregateName,
                TraceAttributes::AGGREGATE_ID => $aggregate->aggregateRootId()->toString(),
            ],
        );
    }
}
