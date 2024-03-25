<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Debug\Trace\TraceHeader;
use Patchlevel\EventSourcing\Metadata\ClassFinder;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\NewStreamStartHeader;
use ReflectionClass;

use function count;

final class AttributeMessageHeaderRegistryFactory implements MessageHeaderRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): MessageHeaderRegistry
    {
        $pathBasedClasses = (new ClassFinder())->findClassNames($paths);
        $classes = array_merge($this->getBasicHeaders(), $pathBasedClasses);
        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Header::class);

            if (count($attributes) === 0) {
                continue;
            }

            $aggregateName = $attributes[0]->newInstance()->name;
            $result[$aggregateName] = $class;
        }

        return new MessageHeaderRegistry($result);
    }

    private function getBasicHeaders(): array
    {
        return [
            AggregateHeader::class,
            NewStreamStartHeader::class,
            ArchivedHeader::class,
            TraceHeader::class,
        ];
    }
}
