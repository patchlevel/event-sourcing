<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\ChildAggregate as ChildAggregateAttribute;
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

final class AttributeChildAggregateMetadataFactory implements ChildAggregateMetadataFactory
{
    /** @var array<class-string<ChildAggregate>, ChildAggregateMetadata> */
    private array $aggregateMetadata = [];

    /**
     * @param class-string<T> $aggregate
     *
     * @return ChildAggregateMetadata<T>
     *
     * @template T of ChildAggregate
     */
    public function metadata(string $aggregate): ChildAggregateMetadata
    {
        if (array_key_exists($aggregate, $this->aggregateMetadata)) {
            return $this->aggregateMetadata[$aggregate];
        }

        $reflectionClass = new ReflectionClass($aggregate);

        $aggregateName = $this->findChildAggregateName($reflectionClass);
        [$suppressEvents, $suppressAll] = $this->findSuppressMissingApply($reflectionClass);
        $applyMethods = $this->findApplyMethods($reflectionClass, $aggregate);

        $metadata = new ChildAggregateMetadata(
            $aggregate,
            $aggregateName,
            $applyMethods,
            $suppressEvents,
            $suppressAll,
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

    private function findChildAggregateName(ReflectionClass $reflector): string
    {
        $attributeReflectionList = $reflector->getAttributes(ChildAggregateAttribute::class);

        if (!$attributeReflectionList) {
            throw new ClassIsNotAChildAggregate($reflector->getName());
        }

        $aggregateAttribute = $attributeReflectionList[0]->newInstance();

        return $aggregateAttribute->name;
    }

    /**
     * @param class-string<ChildAggregate> $aggregate
     *
     * @return array<class-string, string>
     */
    private function findApplyMethods(ReflectionClass $reflector, string $aggregate): array
    {
        $applyMethods = [];

        $methods = $reflector->getMethods();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            $methodName = $method->getName();
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
                    throw new DuplicateEmptyApplyAttribute($methodName);
                }

                $hasOneEmptyApply = true;
                $eventClasses = array_merge($eventClasses, $this->getEventClassesByPropertyTypes($method));
            }

            if ($hasOneEmptyApply && $hasOneNonEmptyApply) {
                throw new MixedApplyAttributeUsage($methodName);
            }

            foreach ($eventClasses as $eventClass) {
                if (!class_exists($eventClass)) {
                    throw new ArgumentTypeIsNotAClass($methodName, $eventClass);
                }

                if (array_key_exists($eventClass, $applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $aggregate,
                        $eventClass,
                        $applyMethods[$eventClass],
                        $methodName,
                    );
                }

                $applyMethods[$eventClass] = $methodName;
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
