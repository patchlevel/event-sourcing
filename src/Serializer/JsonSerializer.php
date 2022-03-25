<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use JsonException;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use ReflectionClass;
use TypeError;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class JsonSerializer implements Serializer
{
    private EventMetadataFactory $metadataFactory;

    private bool $prettyPrint;

    /** @var array<class-string, ReflectionClass> */
    private array $reflectionClassCache = [];

    public function __construct(array $eventClasses, ?EventMetadataFactory $metadataFactory = null, bool $prettyPrint = false)
    {
        $this->metadataFactory = $metadataFactory ?? new AttributeEventMetadataFactory();
        $this->prettyPrint = $prettyPrint;
    }

    public function serialize(object $event): string
    {
        $data = $this->extract($event);

        $flags = JSON_THROW_ON_ERROR;

        if ($this->prettyPrint) {
            $flags = $flags | JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($data, $flags);
        } catch (JsonException $e) {
            throw new SerializationNotPossible($event, $e);
        }
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    public function deserialize(string $class, string $data): object
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DeserializationNotPossible($class, $data, $e);
        }

        return $this->hydrate($class, $payload);
    }

    /**
     * @param class-string<T> $class
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
            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($propertyMetadata->normalizer) {
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
    public function extract(object $object): array
    {
        $metadata = $this->metadataFactory->metadata($object::class);

        $data = [];

        foreach ($metadata->properties as $propertyMetadata) {
            $value = $propertyMetadata->reflection->getValue($object);

            if ($propertyMetadata->normalizer) {
                $value = $propertyMetadata->normalizer->normalize($value);
            }

            $data[$propertyMetadata->fieldName] = $value;
        }

        return $data;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @return T
     */
    private function newInstance(string $class): object
    {
        if (!array_key_exists($class, $this->reflectionClassCache)) {
            $this->reflectionClassCache[$class] = new ReflectionClass($class);
        }

        return $this->reflectionClassCache[$class]->newInstanceWithoutConstructor();
    }
}
