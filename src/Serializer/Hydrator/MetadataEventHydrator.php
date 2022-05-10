<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use ReflectionClass;
use Throwable;
use TypeError;

use function array_key_exists;
use function assert;

final class MetadataEventHydrator implements EventHydrator
{
    private EventMetadataFactory $metadataFactory;

    /** @var array<class-string, ReflectionClass> */
    private array $reflectionClassCache = [];

    public function __construct(EventMetadataFactory $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object
    {
        $metadata = $this->metadataFactory->metadata($class);
        $object = $this->newInstance($class);

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
                $propertyMetadata->reflection->setValue($object, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $class,
                    $propertyMetadata->reflection->getName(),
                    $e
                );
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        $metadata = $this->metadataFactory->metadata($object::class);

        $data = [];

        foreach ($metadata->properties as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $propertyMetadata->reflection->getValue($object);

            if ($propertyMetadata->normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $propertyMetadata->normalizer->normalize($value);
                } catch (Throwable $e) {
                    throw new NormalizationFailure(
                        $object::class,
                        $propertyMetadata->reflection->getName(),
                        $propertyMetadata->normalizer::class,
                        $e
                    );
                }
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
     * @template T of object
     */
    private function newInstance(string $class): object
    {
        if (!array_key_exists($class, $this->reflectionClassCache)) {
            $this->reflectionClassCache[$class] = new ReflectionClass($class);
        }

        $object = $this->reflectionClassCache[$class]->newInstanceWithoutConstructor();

        assert($object instanceof $class);

        return $object;
    }
}
