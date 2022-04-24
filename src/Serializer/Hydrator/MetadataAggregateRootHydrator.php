<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use ReflectionClass;
use TypeError;

use function array_key_exists;
use function assert;

final class MetadataAggregateRootHydrator implements AggregateRootHydrator
{
    /** @var array<class-string, ReflectionClass> */
    private array $reflectionClassCache = [];

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of AggregateRoot
     */
    public function hydrate(string $class, array $data): AggregateRoot
    {
        $metadata = $class::metadata();
        $object = $this->newInstance($class);

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($propertyMetadata->normalizer) {
                /** @psalm-suppress MixedAssignment */
                $value = $propertyMetadata->normalizer->denormalize($value);
            }

            try {
                $propertyMetadata->reflection->setValue($object, $value);
            } catch (TypeError $error) {
                throw new TypeMismatch($error->getMessage(), 0, $error);
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(AggregateRoot $object): array
    {
        $metadata = $object::metadata();

        $data = [];

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $propertyMetadata->reflection->getValue($object);

            if ($propertyMetadata->normalizer) {
                /** @psalm-suppress MixedAssignment */
                $value = $propertyMetadata->normalizer->normalize($value);
            }

            /** @psalm-suppress MixedAssignment */
            $data[$propertyMetadata->fieldName] = $value;
        }

        return $data;
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of AggregateRoot
     */
    private function newInstance(string $class): AggregateRoot
    {
        if (!array_key_exists($class, $this->reflectionClassCache)) {
            $this->reflectionClassCache[$class] = new ReflectionClass($class);
        }

        $object = $this->reflectionClassCache[$class]->newInstanceWithoutConstructor();

        assert($object instanceof $class);

        return $object;
    }
}
