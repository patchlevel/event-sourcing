<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Store\Store;

use function array_key_exists;
use function count;
use function is_subclass_of;

final class DefaultRepository implements Repository
{
    private Store $store;
    private EventBus $eventBus;

    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;

    /** @var array<string, AggregateRoot> */
    private array $instances = [];

    /**
     * @param class-string $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventBus,
        string $aggregateClass
    ) {
        if (!is_subclass_of($aggregateClass, AggregateRoot::class)) {
            throw InvalidAggregateClass::notAggregateRoot($aggregateClass);
        }

        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->aggregateClass = $aggregateClass;
    }

    public function load(string $id): AggregateRoot
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $messages = $this->store->load($this->aggregateClass, $id);

        if (count($messages) === 0) {
            throw new AggregateNotFound($this->aggregateClass, $id);
        }

        return $this->instances[$id] = $this->aggregateClass::createFromMessages($messages);
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
            throw new WrongAggregate($aggregate::class, $this->aggregateClass);
        }

        $messages = $aggregate->releaseMessages();

        if (count($messages) === 0) {
            return;
        }

        $this->store->save(...$messages);
        $this->eventBus->dispatch(...$messages);
    }
}
