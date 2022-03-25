<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

use function array_key_exists;
use function array_map;
use function in_array;

final class AttributeAggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    /** @var array<class-string<AggregateRoot>, AggregateRootMetadata> */
    private array $aggregateMetadata = [];

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (array_key_exists($aggregate, $this->aggregateMetadata)) {
            return $this->aggregateMetadata[$aggregate];
        }

        $metadata = new AggregateRootMetadata();

        $reflector = new ReflectionClass($aggregate);
        $attributes = $reflector->getAttributes(SuppressMissingApply::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll()) {
                $metadata->suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents() as $event) {
                $metadata->suppressEvents[$event] = true;
            }
        }

        $methods = $reflector->getMethods();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            $eventClassesByProperties = $this->getEventClassesByPropertyTypes($method);
            $eventClassesByAttributes = $this->getEventClassesByAttributes($attributes);

            $eventClasses = $eventClassesByAttributes;

            foreach ($eventClassesByProperties as $classesByProperty) {
                if (in_array($classesByProperty, $eventClassesByAttributes)) {
                    continue;
                }

                $eventClasses[] = $classesByProperty;
            }

            foreach ($eventClasses as $eventClass) {
                if (array_key_exists($eventClass, $metadata->applyMethods)) {
                    throw new DuplicateApplyMethod(
                        $aggregate,
                        $eventClass,
                        $metadata->applyMethods[$eventClass],
                        $method->getName()
                    );
                }

                $metadata->applyMethods[$eventClass] = $method->getName();
            }
        }

        $this->aggregateMetadata[$aggregate] = $metadata;

        return $metadata;
    }

    /**
     * @return list<class-string>
     */
    private function getEventClassesByPropertyTypes(ReflectionMethod $method): array
    {
        $propertyType = $method->getParameters()[0]?->getType();

        if ($propertyType === null || $propertyType instanceof ReflectionIntersectionType) {
            throw new RuntimeException();
        }

        if ($propertyType instanceof ReflectionNamedType) {
            return [$propertyType->getName()];
        }

        if ($propertyType instanceof ReflectionUnionType) {
            return array_map(
                static fn (ReflectionNamedType $reflectionType) => $reflectionType->getName(),
                $propertyType->getTypes()
            );
        }

        throw new RuntimeException();
    }

    /**
     * @param array<ReflectionAttribute> $attributes
     *
     * @return list<class-string>
     */
    private function getEventClassesByAttributes(array $attributes): array
    {
        return array_map(static fn (ReflectionAttribute $attribute) => $attribute->newInstance()->eventClass(), $attributes);
    }
}
