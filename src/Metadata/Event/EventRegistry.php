<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use function array_flip;
use function array_key_exists;

final class EventRegistry
{
    /** @var array<string, class-string> */
    private array $eventClassMap;

    /** @var array<class-string, string> */
    private array $eventClassMapRevert;

    /**
     * @param array<string, class-string> $eventClassMap
     */
    public function __construct(array $eventClassMap)
    {
        $this->eventClassMap = $eventClassMap;
        $this->eventClassMapRevert = array_flip($eventClassMap);
    }

    /**
     * @param class-string $eventClass
     */
    public function eventName(string $eventClass): string
    {
        if (!array_key_exists($eventClass, $this->eventClassMapRevert)) {
            throw new EventClassNotRegistered($eventClass);
        }

        return $this->eventClassMapRevert[$eventClass];
    }

    /**
     * @return class-string
     */
    public function eventClass(string $eventName): string
    {
        if (!array_key_exists($eventName, $this->eventClassMap)) {
            throw new EventNameNotRegistered($eventName);
        }

        return $this->eventClassMap[$eventName];
    }
}
