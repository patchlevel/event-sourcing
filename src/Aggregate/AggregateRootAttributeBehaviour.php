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

            $child = $this->getChildAggregateByPropertyName($property);

            if ($child !== null) {
                $child->$method($event);
            }
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

        foreach ($metadata->childAggregates as $propertyName) {
            $child = $this->getChildAggregateByPropertyName($propertyName);

            if ($child === null) {
                continue;
            }

            $child->setRecorder($this->recorder);
        }
    }

    private function getChildAggregateByPropertyName(string $propertyName): ChildAggregate|null
    {
        $reflectionProperty = new ReflectionProperty($this::class, $propertyName);

        if (!$reflectionProperty->isInitialized($this)) {
            return null;
        }

        /** @var ChildAggregate|null $child */
        $child = $reflectionProperty->getValue($this);

        return $child;
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
