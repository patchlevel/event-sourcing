<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function count;

class AttributeEventClassLoader implements EventClassLoader
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, class-string>
     */
    public function load(array $paths): array
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Event::class);

            if (count($attributes) === 0) {
                continue;
            }

            $eventName = $attributes[0]->newInstance()->name();

            $result[$eventName] = $class;
        }

        return $result;
    }
}
