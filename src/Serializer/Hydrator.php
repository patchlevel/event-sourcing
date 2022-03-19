<?php

namespace Patchlevel\EventSourcing\Serializer;

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

            $property->setValue($object, $data[$fieldName] ?? null);
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

            $fieldName = $this->fieldName($property);
            $data[$fieldName] = $property->getValue($object);
        }

        return $data;
    }

    private function fieldName(ReflectionProperty $property): string
    {
        return $property->getName();
    }
}