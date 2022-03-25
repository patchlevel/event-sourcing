<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\FieldName;
use Patchlevel\EventSourcing\Attribute\Normalize;
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

        $metadata = new EventMetadata();
        $reflectionClass = new ReflectionClass($event);

        $attributeReflectionList = $reflectionClass->getAttributes(Event::class);

        if (!$attributeReflectionList) {
            throw new ClassIsNotAnEvent($event);
        }

        $eventAttribute = $attributeReflectionList[0]->newInstance();
        $metadata->name = $eventAttribute->name();

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);

            $propertyMetadata = new EventPropertyMetadata();
            $propertyMetadata->reflection = $property;
            $propertyMetadata->fieldName = $property->getName();

            $attributeReflectionList = $property->getAttributes(FieldName::class);

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $propertyMetadata->fieldName = $attribute->name();
            }

            $attributeReflectionList = $property->getAttributes(Normalize::class);

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $propertyMetadata->normalizer = $attribute->normalizer();
            }

            $metadata->properties[$property->getName()] = $propertyMetadata;
        }

        $this->eventMetadata[$event] = $metadata;

        return $metadata;
    }
}
