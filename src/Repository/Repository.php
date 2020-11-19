<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventStream;
use Patchlevel\EventSourcing\Store\Store;
use ReflectionClass;
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
     * @var class-string
     */
    private string $aggregateClass;

    /**
     * @var AggregateRoot[]
     */
    private array $instances = [];

    
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

        return $this->instances[$id] = $this->createAggregate($this->aggregateClass, $events);
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

        $this->store->save($this->aggregateClass, $aggregate->aggregateRootId(), $eventStream);

        foreach ($eventStream as $event) {
            $this->eventStream->dispatch($event);
        }
    }

    /**
     * @psalm-assert class-string $class
     */
    private function assertExtendsEventSourcedAggregateRoot(string $class): void
    {
        if (is_subclass_of($class, AggregateRoot::class) === false) {
            throw new InvalidArgumentException(sprintf("Class '%s' is not an EventSourcedAggregateRoot.", $class));
        }
    }

    /**
     * @param class-string $class
     * @param array<AggregateChanged> $eventStream
     */
    private function createAggregate(string $class, array $eventStream): AggregateRoot
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('class "%s" not found', $class));
        }

        $reflectionClass = new ReflectionClass($class);

        /** @var AggregateRoot $aggregate */
        $aggregate = $reflectionClass->newInstanceWithoutConstructor();
        $aggregate->initializeState($eventStream);

        return $aggregate;
    }
}
