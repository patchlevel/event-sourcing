<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot as AttributeSnapshot;
use Patchlevel\EventSourcing\Attribute\Stream;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

use function array_key_exists;
use function array_map;
use function array_merge;
use function class_exists;
use function is_a;

final class AttributeAggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    /** @var array<class-string<AggregateRoot>, AggregateRootMetadata> */
    private array $aggregateMetadata = [];

    /**
     * @param class-string<T> $aggregate
     *
     * @return AggregateRootMetadata<T>
     *
     * @template T of AggregateRoot
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (array_key_exists($aggregate, $this->aggregateMetadata)) {
            return $this->aggregateMetadata[$aggregate];
        }

        $reflectionClass = new ReflectionClass($aggregate);

        $aggregateName = $this->findAggregateName($reflectionClass);
        $idProperty = $this->findIdProperty($reflectionClass);
        [$suppressEvents, $suppressAll] = $this->findSuppressMissingApply($reflectionClass);
        $applyMethods = $this->findApplyMethods($reflectionClass, $aggregate);
        $snapshot = $this->findSnapshot($reflectionClass);

        $metadata = new AggregateRootMetadata(
            $aggregate,
            $aggregateName,
            $idProperty,
            $applyMethods,
            $suppressEvents,
            $suppressAll,
            $snapshot,
            $this->findStreamName($reflectionClass),
        );

        $this->aggregateMetadata[$aggregate] = $metadata;

        return $metadata;
    }

    /** @return array{array<class-string, true>, bool} */
    private function findSuppressMissingApply(ReflectionClass $reflector): array
    {
        $suppressEvents = [];
        $suppressAll = false;

        $attributes = $reflector->getAttributes(SuppressMissingApply::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll) {
                $suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents as $event) {
                $suppressEvents[$event] = true;
            }
        }

        return [$suppressEvents, $suppressAll];
    }

    private function findAggregateName(ReflectionClass $reflector): string
    {
        $attributeReflectionList = $reflector->getAttributes(Aggregate::class);

        if (!$attributeReflectionList) {
            throw new ClassIsNotAnAggregate($reflector->getName());
        }

        $aggregateAttribute = $attributeReflectionList[0]->newInstance();

        return $aggregateAttribute->name;
    }

    private function findIdProperty(ReflectionClass $reflector): string
    {
        $properties = $reflector->getProperties();

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Id::class);

            if ($attributes === []) {
                continue;
            }

            return $property->getName();
        }

        throw new AggregateRootIdNotFound($reflector->getName());
    }

    private function findSnapshot(ReflectionClass $reflector): Snapshot|null
    {
        $attributeReflectionList = $reflector->getAttributes(AttributeSnapshot::class);

        if (!$attributeReflectionList) {
            return null;
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return new Snapshot(
            $attribute->name,
            $attribute->batch,
            $attribute->version,
        );
    }

    private function findStreamName(ReflectionClass $reflector): string|null
    {
        $attributes = $reflector->getAttributes(Stream::class);

        if ($attributes === []) {
            return null;
        }

        $streamName = $attributes[0]->newInstance()->name;

        if (class_exists($streamName) && is_a($streamName, AggregateRoot::class, true)) {
            return $this->metadata($streamName)->streamName;
        }

        return $attributes[0]->newInstance()->name;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return array<class-string, string>
     */
    private function findApplyMethods(ReflectionClass $reflector, string $aggregate): array
    {
        $applyMethods = [];

        // process apply methods
        foreach ($reflector->getMethods() as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            $eventClasses = [];
            $hasOneEmptyApply = false;
            $hasOneNonEmptyApply = false;

            foreach ($attributes as $attribute) {
                $applyAttribute = $attribute->newInstance();
                $eventClass = $applyAttribute->eventClass;

                if ($eventClass !== null) {
                    $hasOneNonEmptyApply = true;
                    $eventClasses[] = $eventClass;

                    continue;
                }

                if ($hasOneEmptyApply) {
                    throw new DuplicateEmptyApplyAttribute($method->getName());
                }

                $hasOneEmptyApply = true;
                $eventClasses = array_merge($eventClasses, $this->getEventClassesByPropertyTypes($method));
            }

            if ($hasOneEmptyApply && $hasOneNonEmptyApply) {
                throw new MixedApplyAttributeUsage($method->getName());
            }

            foreach ($eventClasses as $eventClass) {
                if (!class_exists($eventClass)) {
                    throw new ArgumentTypeIsNotAClass($method->getName(), $eventClass);
                }

                if (array_key_exists($eventClass, $applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $aggregate,
                        $eventClass,
                        $applyMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $applyMethods[$eventClass] = $method->getName();
            }
        }

        return $applyMethods;
    }

    /** @return array<string> */
    private function getEventClassesByPropertyTypes(ReflectionMethod $method): array
    {
        $propertyType = $method->getParameters()[0]->getType();
        $methodName = $method->getName();

        if ($propertyType === null) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionIntersectionType) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        if ($propertyType instanceof ReflectionNamedType) {
            return [$propertyType->getName()];
        }

        if ($propertyType instanceof ReflectionUnionType) {
            return array_map(
                static function (ReflectionNamedType|ReflectionIntersectionType $reflectionType) use ($methodName): string {
                    if ($reflectionType instanceof ReflectionIntersectionType) {
                        throw new ArgumentTypeIsMissing($methodName);
                    }

                    return $reflectionType->getName();
                },
                $propertyType->getTypes(),
            );
        }

        return [];
    }
}
