<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Attribute\SerializedName;
use ReflectionClass;

use function array_key_exists;

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

        $properties = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);

            $reflection = $property;
            $fieldName = $property->getName();

            $attributeReflectionList = $property->getAttributes(SerializedName::class);

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

        $this->eventMetadata[$event] = new EventMetadata(
            $eventName,
            $properties
        );

        return $this->eventMetadata[$event];
    }
}
