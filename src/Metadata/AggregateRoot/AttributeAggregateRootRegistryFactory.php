<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function count;
use function is_subclass_of;

class AttributeAggregateRootRegistryFactory implements AggregateRootRegistryFactory
{
    /**
     * @param list<string> $paths
     */
    public function create(array $paths): AggregateRootRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Aggregate::class);

            if (count($attributes) === 0) {
                continue;
            }

            if (!is_subclass_of($class, AggregateRoot::class)) {
                throw new NoAggregateRoot($class);
            }

            $aggregateName = $attributes[0]->newInstance()->name();

            $result[$aggregateName] = $class;
        }

        return new AggregateRootRegistry($result);
    }
}
