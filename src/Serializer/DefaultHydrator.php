<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Attribute\Normalize;
use ReflectionClass;
use ReflectionProperty;
use TypeError;

use function array_key_exists;
use function assert;

final class DefaultHydrator implements Hydrator
{
    /** @var array<class-string, array<string, array{reflection: ReflectionProperty, fieldName: string, normalizer: ?Normalizer}>> */
    private static array $propertyMetadataCache = [];

    /** @var array<class-string, ReflectionClass> */
    private static array $reflectionClassCache = [];

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
        $object = self::reflectionClass($class)->newInstanceWithoutConstructor();
        assert($object instanceof $class);

        $metadata = self::propertyMetadata($class);

        foreach ($metadata as $propertyMetadata) {
            $value = $data[$propertyMetadata['fieldName']] ?? null;

            if ($propertyMetadata['normalizer']) {
                $value = $propertyMetadata['normalizer']->denormalize($value);
            }

            try {
                $propertyMetadata['reflection']->setValue($object, $value);
            } catch (TypeError $error) {
                throw new TypeMismatch($error->getMessage(), 0, $error);
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        $metadata = self::propertyMetadata($object::class);

        $data = [];

        foreach ($metadata as $propertyMetadata) {
            $value = $propertyMetadata['reflection']->getValue($object);

            if ($propertyMetadata['normalizer']) {
                $value = $propertyMetadata['normalizer']->normalize($value);
            }

            $data[$propertyMetadata['fieldName']] = $value;
        }

        return $data;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, array{reflection: ReflectionProperty, fieldName: string, normalizer: ?Normalizer}>
     */
    private static function propertyMetadata(string $class): array
    {
        if (array_key_exists($class, self::$propertyMetadataCache)) {
            return self::$propertyMetadataCache[$class];
        }

        $reflectionClass = self::reflectionClass($class);
        $metadata = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);

            $normalizer = null;
            $attributeReflectionList = $property->getAttributes(Normalize::class);

            if ($attributeReflectionList !== []) {
                $attribute = $attributeReflectionList[0]->newInstance();
                $normalizer = $attribute->normalizer();
            }

            $metadata[$property->getName()] = [
                'reflection' => $property,
                'fieldName' => $property->getName(),
                'normalizer' => $normalizer,
            ];
        }

        self::$propertyMetadataCache[$class] = $metadata;

        return $metadata;
    }

    /**
     * @param class-string $class
     */
    private static function reflectionClass(string $class): ReflectionClass
    {
        if (!array_key_exists($class, self::$reflectionClassCache)) {
            self::$reflectionClassCache[$class] = new ReflectionClass($class);
        }

        return self::$reflectionClassCache[$class];
    }
}
