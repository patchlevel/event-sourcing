<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use ReflectionProperty;

use function array_key_exists;
use function count;
use function explode;

trait AggregateRootAttributeBehaviour
{
    use AggregateRootBehaviour;
    use AggregateRootMetadataAwareBehaviour;

    #[Ignore]
    private AggregateRootId|null $cachedAggregateRootId = null;

    /** @var (callable(object $event): void)|null */
    #[Ignore]
    private $recorder = null;

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

        if ($metadata->childAggregates === []) {
            $this->$method($event);

            return;
        }

        $parts = explode('.', $method);

        if (count($parts) === 2) {
            [$property, $method] = $parts;

            /** @var ChildAggregate $child */
            $child = $this->$property;
            $child->$method($event);
        } else {
            $this->$method($event);
        }

        $this->passRecorderToChildAggregates();
    }

    #[PostHydrate]
    private function passRecorderToChildAggregates(): void
    {
        $metadata = static::metadata();
        $this->recorder ??= $this->recordThat(...);

        foreach ($metadata->childAggregates as $property) {
            if (!isset($this->{$property})) {
                continue;
            }

            /** @var ChildAggregate $child */
            $child = $this->{$property};
            $child->setRecorder($this->recorder);
        }
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
}
