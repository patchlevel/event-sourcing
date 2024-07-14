<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;
use ReflectionProperty;

use function array_key_exists;

trait AggregateRootAttributeBehaviour
{
    use AggregateRootBehaviour;
    use AggregateRootMetadataAwareBehaviour;

    #[Ignore]
    private AggregateRootId|null $cachedAggregateRootId = null;

    protected function apply(object $event): void
    {
        $metadata = static::metadata();

        if (!array_key_exists($event::class, $metadata->applyMethods)) {
            if (!$metadata->suppressAll && !array_key_exists($event::class, $metadata->suppressEvents)) {
                throw new ApplyMethodNotFound($this::class, $event::class);
            }

            return;
        }

        $method = $metadata->applyMethods[$event::class];
        $this->$method($event);
    }

    public function aggregateRootId(): AggregateRootId
    {
        if ($this->cachedAggregateRootId instanceof AggregateRootId) {
            return $this->cachedAggregateRootId;
        }

        $metadata = static::metadata();

        $reflection = new ReflectionProperty($this, $metadata->idProperty);

        /** @var mixed $aggregateRootId */
        $aggregateRootId = $reflection->getValue($this);

        if (!$aggregateRootId instanceof AggregateRootId) {
            throw new AggregateRootIdNotSupported($this::class, $aggregateRootId);
        }

        return $this->cachedAggregateRootId = $aggregateRootId;
    }

    /**
     * @return array<ChildAggregate>
     */
    public function getChildren(): array
    {
        $metadata = static::metadata();
        $childs = [];

        foreach ($metadata->childAggregates as $property) {
            $childs[] = $this->{$property};
        }

        return $childs;
    }
}
