<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventStream;
use Patchlevel\EventSourcing\Store\Store;
use function array_key_exists;
use function class_exists;
use function count;
use function is_subclass_of;
use function sprintf;

final class Repository
{
    private Store $store;
    private EventStream $eventStream;

    /**
     * @psalm-var class-string
     */
    private string $aggregateClass;

    /**
     * @var AggregateRoot[]
     */
    private array $instances = [];

    /**
     * @psalm-param class-string $aggregateClass
     */
    public function __construct(
        Store $store,
        EventStream $eventStream,
        string $aggregateClass
    ) {
        $this->assertExtendsEventSourcedAggregateRoot($aggregateClass);

        $this->store = $store;
        $this->eventStream = $eventStream;
        $this->aggregateClass = $aggregateClass;
    }

    public function load(string $id): AggregateRoot
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $events = $this->store->load($this->aggregateClass, $id);

        if (count($events) === 0) {
            throw new AggregateNotFoundException($this->aggregateClass, $id);
        }

        return $this->instances[$id] = $this->createAggregate($events);
    }

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->instances)) {
            return true;
        }

        return $this->store->has($this->aggregateClass, $id);
    }

    public function save(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof $this->aggregateClass) {
            throw new WrongAggregateException(get_class($aggregate), $this->aggregateClass);
        }

        $eventStream = $aggregate->releaseEvents();

        if (count($eventStream) === 0) {
            return;
        }

        $this->store->saveBatch($this->aggregateClass, $aggregate->aggregateRootId(), $eventStream);

        foreach ($eventStream as $event) {
            $this->eventStream->dispatch($event);
        }
    }

    /**
     * @psalm-assert class-string<AggregateRoot> $class
     */
    private function assertExtendsEventSourcedAggregateRoot(string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('class "%s" not found', $class));
        }

        if (is_subclass_of($class, AggregateRoot::class) === false) {
            throw new InvalidArgumentException(sprintf("Class '%s' is not an EventSourcedAggregateRoot.", $class));
        }
    }

    /**
     * @param array<AggregateChanged> $eventStream
     */
    private function createAggregate(array $eventStream): AggregateRoot
    {
        $class = $this->aggregateClass;

        if (is_subclass_of($class, AggregateRoot::class) === false) {
            throw new InvalidArgumentException(sprintf("Class '%s' is not an EventSourcedAggregateRoot.", $class));
        }

        return $class::createFromEventStream($eventStream);
    }
}
