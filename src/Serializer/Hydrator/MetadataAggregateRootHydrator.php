<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use TypeError;

use function array_key_exists;
use function assert;
use function is_a;
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
        if (!is_a($class, BasicAggregateRoot::class, true)) {
            throw new RuntimeException();
        }

        $metadata = $class::metadata();
        $aggregateRoot = $this->newInstance($class);

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($propertyMetadata->normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $propertyMetadata->normalizer->denormalize($value);
                } catch (Throwable $e) {
                    throw new DenormalizationFailure(
                        $class,
                        $propertyMetadata->reflection->getName(),
                        $propertyMetadata->normalizer::class,
                        $e
                    );
                }
            }

            try {
                $propertyMetadata->reflection->setValue($aggregateRoot, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $class,
                    $propertyMetadata->reflection->getName(),
                    $e
                );
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
        if (!$aggregateRoot instanceof BasicAggregateRoot) {
            throw new RuntimeException();
        }

        $metadata = $aggregateRoot::metadata();

        $data = [];

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $propertyMetadata->reflection->getValue($aggregateRoot);

            if ($propertyMetadata->normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $propertyMetadata->normalizer->normalize($value);
                } catch (Throwable $e) {
                    throw new NormalizationFailure(
                        $aggregateRoot::class,
                        $propertyMetadata->reflection->getName(),
                        $propertyMetadata->normalizer::class,
                        $e
                    );
                }
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
     * @template T of BasicAggregateRoot
     */
    private function newInstance(string $class): BasicAggregateRoot
    {
        if (!array_key_exists($class, $this->reflectionClassCache)) {
            $this->reflectionClassCache[$class] = new ReflectionClass($class);
        }

        $object = $this->reflectionClassCache[$class]->newInstanceWithoutConstructor();

        assert($object instanceof $class);

        return $object;
    }

    private function setPlayhead(BasicAggregateRoot $aggregateRoot, int $playhead): void
    {
        if ($this->playheadReflection === null) {
            $this->playheadReflection = new ReflectionProperty(BasicAggregateRoot::class, 'playhead');
        }

        $this->playheadReflection->setValue($aggregateRoot, $playhead);
    }
}
