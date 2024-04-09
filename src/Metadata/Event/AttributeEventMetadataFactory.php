<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\DataSubjectId;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\PersonalData;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;
use function count;

final class AttributeEventMetadataFactory implements EventMetadataFactory
{
    /** @var array<class-string, EventMetadata> */
    private array $eventMetadata = [];

    /** @param class-string $event */
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

        $propertyMetadataList = [];
        $hasPersonalData = false;

        $subjectId = $this->subjectIdField($reflectionClass);

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propertyMetadata = $this->propertyMetadata($reflectionProperty);

            if ($propertyMetadata->isPersonalData) {
                if ($subjectId === $propertyMetadata->fieldName) {
                    throw new SubjectIdAndPersonalDataConflict($event, $propertyMetadata->fieldName);
                }

                $hasPersonalData = true;
            }

            $propertyMetadataList[$reflectionProperty->getName()] = $propertyMetadata;
        }

        if ($hasPersonalData && $subjectId === null) {
            throw new MissingDataSubjectId($event);
        }

        $eventAttribute = $attributeReflectionList[0]->newInstance();

        $this->eventMetadata[$event] = new EventMetadata(
            $eventAttribute->name,
            $this->splitStream($reflectionClass),
            $subjectId,
            $propertyMetadataList,
        );

        return $this->eventMetadata[$event];
    }

    private function splitStream(ReflectionClass $reflectionClass): bool
    {
        return count($reflectionClass->getAttributes(SplitStream::class)) !== 0;
    }

    private function propertyMetadata(ReflectionProperty $reflectionProperty): PropertyMetadata
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(PersonalData::class);

        if (!$attributeReflectionList) {
            return new PropertyMetadata(
                $reflectionProperty->getName(),
                $this->fieldName($reflectionProperty),
            );
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return new PropertyMetadata(
            $reflectionProperty->getName(),
            $this->fieldName($reflectionProperty),
            true,
            $attribute->fallback,
        );
    }

    private function subjectIdField(ReflectionClass $reflectionClass): string|null
    {
        $property = null;

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $attributeReflectionList = $reflectionProperty->getAttributes(DataSubjectId::class);

            if (!$attributeReflectionList) {
                continue;
            }

            if ($property !== null) {
                throw new MultipleDataSubjectId($property->getName(), $reflectionProperty->getName());
            }

            $property = $reflectionProperty;
        }

        if ($property === null) {
            return null;
        }

        return $this->fieldName($property);
    }

    private function fieldName(ReflectionProperty $reflectionProperty): string
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(NormalizedName::class);

        if (!$attributeReflectionList) {
            return $reflectionProperty->getName();
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return $attribute->name();
    }
}
