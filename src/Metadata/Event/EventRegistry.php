<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use function array_flip;
use function array_key_exists;

final class EventRegistry
{
    /** @var array<string, class-string> */
    private array $nameToClassMap;

    /** @var array<class-string, string> */
    private array $classToNameMap;

    /** @param array<string, class-string> $eventNameToClassMap */
    public function __construct(array $eventNameToClassMap)
    {
        $this->nameToClassMap = $eventNameToClassMap;
        $this->classToNameMap = array_flip($eventNameToClassMap);
    }

    /** @param class-string $eventClass */
    public function eventName(string $eventClass): string
    {
        if (!array_key_exists($eventClass, $this->classToNameMap)) {
            throw new EventClassNotRegistered($eventClass);
        }

        return $this->classToNameMap[$eventClass];
    }

    /** @return class-string */
    public function eventClass(string $eventName): string
    {
        if (!array_key_exists($eventName, $this->nameToClassMap)) {
            throw new EventNameNotRegistered($eventName);
        }

        return $this->nameToClassMap[$eventName];
    }

    public function hasEventClass(string $eventClass): bool
    {
        return array_key_exists($eventClass, $this->classToNameMap);
    }

    public function hasEventName(string $eventName): bool
    {
        return array_key_exists($eventName, $this->nameToClassMap);
    }

    /** @return array<string, class-string> */
    public function eventClasses(): array
    {
        return $this->nameToClassMap;
    }

    /** @return array<class-string, string> */
    public function eventNames(): array
    {
        return $this->classToNameMap;
    }
}
