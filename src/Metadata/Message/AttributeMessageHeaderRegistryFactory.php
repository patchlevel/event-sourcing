<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use ReflectionClass;

use function array_key_exists;
use function count;

final class AttributeMessageHeaderRegistryFactory implements MessageHeaderRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): MessageHeaderRegistry
    {
        $classes = (new ClassFinder())->findClassNames($paths);

        $names = [];
        $aliases = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Header::class);

            if (count($attributes) === 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $names[$attribute->name] = $class;

            foreach ($attribute->aliases as $alias) {
                if (array_key_exists($alias, $aliases)) {
                    throw new HeaderAlreadyInRegistry($alias);
                }

                $aliases[$alias] = $class;
            }
        }

        $registry = MessageHeaderRegistry::createWithInternalHeaders($names);

        foreach ($aliases as $alias => $class) {
            if ($registry->hasHeaderName($alias)) {
                throw new HeaderAlreadyInRegistry($alias);
            }
        }

        return new MessageHeaderRegistry($registry->headerClasses() + $aliases);
    }
}
