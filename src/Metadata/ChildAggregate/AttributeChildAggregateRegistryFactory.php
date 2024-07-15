<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\ChildAggregate as ChildAggregateAttribute;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function array_key_exists;
use function count;
use function is_subclass_of;

final class AttributeChildAggregateRegistryFactory implements ChildAggregateRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): ChildAggregateRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(ChildAggregateAttribute::class);

            if (count($attributes) === 0) {
                continue;
            }

            if (!is_subclass_of($class, ChildAggregate::class)) {
                throw new NoChildAggregate($class);
            }

            $aggregateName = $attributes[0]->newInstance()->name;

            if (array_key_exists($aggregateName, $result)) {
                throw new ChildAggregateAlreadyInRegistry($aggregateName);
            }

            $result[$aggregateName] = $class;
        }

        return new ChildAggregateRegistry($result);
    }
}
