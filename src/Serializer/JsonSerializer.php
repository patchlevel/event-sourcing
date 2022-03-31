<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use JsonException;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use ReflectionClass;
use TypeError;

use function array_flip;
use function array_key_exists;
use function class_exists;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonSerializer implements Serializer
{
    private EventMetadataFactory $metadataFactory;

    /** @var array<string, class-string> */
    private array $eventClassMap;

    /** @var array<class-string, string> */
    private array $eventClassMapRevert;

    /** @var array<class-string, ReflectionClass> */
    private array $reflectionClassCache = [];

    /**
     * @param array<string, class-string> $eventClassMap
     */
    public function __construct(EventMetadataFactory $metadataFactory, array $eventClassMap = [])
    {
        $this->metadataFactory = $metadataFactory;
        $this->eventClassMap = $eventClassMap;
        $this->eventClassMapRevert = array_flip($eventClassMap);
    }

    public function serialize(object $event, array $options = []): SerializedData
    {
        $data = $this->extract($event);

        $flags = JSON_THROW_ON_ERROR;

        if ($options[self::OPTION_PRETTY_PRINT] ?? false) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return new SerializedData(
                $this->eventClassMapRevert[$event::class] ?? $event::class,
                json_encode($data, $flags)
            );
        } catch (JsonException $e) {
            throw new SerializationNotPossible($event, $e);
        }
    }

    public function deserialize(SerializedData $data, array $options = []): object
    {
        $class = $this->eventClassMap[$data->name] ?? $data->name;

        if (!class_exists($class)) {
            throw new EventClassNotFound($data->name, $class);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($data->payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DeserializationNotPossible($class, $data->payload, $e);
        }

        return $this->hydrate($class, $payload);
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
    public function extract(object $object): array
    {
        $metadata = $this->metadataFactory->metadata($object::class);

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

    /**
     * @param array<string, class-string> $eventClassMap
     */
    public static function createDefault(array $eventClassMap = []): static
    {
        return new self(new AttributeEventMetadataFactory(), $eventClassMap);
    }
}
