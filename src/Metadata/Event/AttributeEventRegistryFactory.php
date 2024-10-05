<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function array_key_exists;
use function count;

final class AttributeEventRegistryFactory implements EventRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): EventRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Event::class);

            if (count($attributes) === 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            if (array_key_exists($attribute->name, $result)) {
                throw new EventAlreadyInRegistry($attribute->name);
            }

            $result[$attribute->name] = $class;

            foreach ($attribute->aliases as $alias) {
                if (array_key_exists($alias, $result)) {
                    throw new EventAlreadyInRegistry($alias);
                }

                $result[$alias] = $class;
            }
        }

        return new EventRegistry($result);
    }
}
