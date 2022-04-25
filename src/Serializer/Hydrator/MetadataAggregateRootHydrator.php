<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use ReflectionClass;
use ReflectionProperty;
use TypeError;

use function array_key_exists;
use function assert;
use function is_int;

final class MetadataAggregateRootHydrator implements AggregateRootHydrator
{
    private const PLAYHEAD_KEY = '_playhead';

    /** @var array<class-string, ReflectionClass> */
    private array $reflectionClassCache = [];

    private ?ReflectionProperty $playheadReflection = null;

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
        $aggregateRoot = $this->newInstance($class);

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($propertyMetadata->normalizer) {
                /** @psalm-suppress MixedAssignment */
                $value = $propertyMetadata->normalizer->denormalize($value);
            }

            try {
                $propertyMetadata->reflection->setValue($aggregateRoot, $value);
            } catch (TypeError $error) {
                throw new TypeMismatch($error->getMessage(), 0, $error);
            }
        }

        if (!array_key_exists(self::PLAYHEAD_KEY, $data) || !is_int($data[self::PLAYHEAD_KEY])) {
            throw new MissingPlayhead();
        }

        $this->setPlayhead($aggregateRoot, $data[self::PLAYHEAD_KEY]);

        return $aggregateRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(AggregateRoot $aggregateRoot): array
    {
        $metadata = $aggregateRoot::metadata();

        $data = [];

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $propertyMetadata->reflection->getValue($aggregateRoot);

            if ($propertyMetadata->normalizer) {
                /** @psalm-suppress MixedAssignment */
                $value = $propertyMetadata->normalizer->normalize($value);
            }

            /** @psalm-suppress MixedAssignment */
            $data[$propertyMetadata->fieldName] = $value;
        }

        $data[self::PLAYHEAD_KEY] = $aggregateRoot->playhead();

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

    private function setPlayhead(AggregateRoot $aggregateRoot, int $playhead): void
    {
        if ($this->playheadReflection === null) {
            $this->playheadReflection = new ReflectionProperty(AggregateRoot::class, 'playhead');
            $this->playheadReflection->setAccessible(true);
        }

        $this->playheadReflection->setValue($aggregateRoot, $playhead);
    }
}
