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
    private AggregateRootId|null $_aggregateRootId = null;

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
        if ($this->_aggregateRootId instanceof AggregateRootId) {
            return $this->_aggregateRootId;
        }

        $metadata = static::metadata();

        $reflection = new ReflectionProperty($this, $metadata->idProperty);

        /** @var mixed $aggregateId */
        $aggregateId = $reflection->getValue($this);

        if (!$aggregateId instanceof AggregateRootId) {
            throw new AggregateIdNotSupported($this::class, $aggregateId);
        }

        return $this->_aggregateRootId = $aggregateId;
    }
}
