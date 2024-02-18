<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\EventBus\Header;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function count;
use function is_subclass_of;

final class AttributeMessageHeaderRegistryFactory implements MessageHeaderRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): MessageHeaderRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(\Patchlevel\EventSourcing\Attribute\Header::class);

            if (count($attributes) === 0) {
                continue;
            }

            if (!is_subclass_of($class, Header::class)) {
                throw new NotAHeaderClass($class);
            }

            $aggregateName = $attributes[0]->newInstance()->name;

            $result[$aggregateName] = $class;
        }

        return new MessageHeaderRegistry($result);
    }
}
