<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use ReflectionClass;

use function array_key_exists;
use function count;

final class AttributeEventMetadataFactory implements EventMetadataFactory
{
    /** @var array<class-string, EventMetadata> */
    private array $eventMetadata = [];

    /**
     * @param class-string $event
     */
    public function metadata(string $event): EventMetadata
    {
        if (array_key_exists($event, $this->eventMetadata)) {
            return $this->eventMetadata[$event];
        }

        $reflectionClass = new ReflectionClass($event);

        $attributeReflectionList = $reflectionClass->getAttributes(Event::class);

        if (!$attributeReflectionList) {
            throw new ClassIsNotAnEvent($event);
        }

        $eventAttribute = $attributeReflectionList[0]->newInstance();
        $eventName = $eventAttribute->name();

        $this->eventMetadata[$event] = new EventMetadata(
            $eventName,
            $this->splitStream($reflectionClass),
        );

        return $this->eventMetadata[$event];
    }

    private function splitStream(ReflectionClass $reflectionClass): bool
    {
        return count($reflectionClass->getAttributes(SplitStream::class)) !== 0;
    }
}
