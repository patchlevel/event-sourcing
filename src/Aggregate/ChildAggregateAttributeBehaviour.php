<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;
use ReflectionProperty;

use function array_key_exists;

trait ChildAggregateAttributeBehaviour
{
    use ChildAggregateBehaviour;
    use ChildAggregateMetadataAwareBehaviour;

    public function apply(object $event): void
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
}
