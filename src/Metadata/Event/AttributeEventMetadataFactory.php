<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Attribute\NormalizedName;
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
            $this->getPropertyMetadataList($reflectionClass),
            $this->splitStream($reflectionClass),
        );

        return $this->eventMetadata[$event];
    }

    private function splitStream(ReflectionClass $reflectionClass): bool
    {
        return count($reflectionClass->getAttributes(SplitStream::class)) !== 0;
    }

    /**
     * @return array<string, EventPropertyMetadata>
     */
    private function getPropertyMetadataList(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $reflection = $property;
            $fieldName = $property->getName();

            $attributeReflectionList = $property->getAttributes(NormalizedName::class);

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $fieldName = $attribute->name();
            }

            $attributeReflectionList = $property->getAttributes(Normalize::class);

            $normalizer = null;

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $normalizer = $attribute->normalizer();
            }

            $properties[$property->getName()] = new EventPropertyMetadata(
                $fieldName,
                $reflection,
                $normalizer
            );
        }

        return $properties;
    }
}
