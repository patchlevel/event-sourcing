<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function count;

final class AttributeMessageHeaderRegistryFactory implements MessageHeaderRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): MessageHeaderRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(HeaderIdentifier::class);

            if (count($attributes) === 0) {
                continue;
            }

            $aggregateName = $attributes[0]->newInstance()->name;
            $result[$aggregateName] = $class;
        }

        return new MessageHeaderRegistry($result);
    }
}
