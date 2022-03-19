<?php

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Attribute\Normalize;
use ReflectionClass;
use ReflectionProperty;

final class Hydrator
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrate(string $class, array $data): object
    {
        $reflectionClass = new ReflectionClass($class);

        $object = $reflectionClass->newInstanceWithoutConstructor();

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);

            $fieldName = $this->fieldName($property);
            $value = $data[$fieldName] ?? null;

            $attributes = $property->getAttributes(Normalize::class);
            foreach ($attributes as $attribute) {
                $attributeInstance = $attribute->newInstance();
                $value = $attributeInstance->normalizer()->denormalize($value);
            }

            $property->setValue($object, $value);
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        $reflectionClass = new ReflectionClass($object);

        $data = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);

            $attributes = $property->getAttributes(Normalize::class);
            foreach ($attributes as $attribute) {
                $attributeInstance = $attribute->newInstance();
                $value = $attributeInstance->normalizer()->normalize($value);
            }

            $fieldName = $this->fieldName($property);
            $data[$fieldName] = $value;
        }

        return $data;
    }

    private function fieldName(ReflectionProperty $property): string
    {
        return $property->getName();
    }
}