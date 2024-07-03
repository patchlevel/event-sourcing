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

            $eventName = $attributes[0]->newInstance()->name;

            if (array_key_exists($eventName, $result)) {
                throw new EventAlreadyInRegistry($eventName);
            }

            $result[$eventName] = $class;
        }

        return new EventRegistry($result);
    }
}
