<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use JsonException;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Hydrator\EventHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\Hydrator;

use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonSerializer implements Serializer
{
    private Hydrator $hydrator;
    private EventRegistry $eventRegistry;

    public function __construct(Hydrator $hydrator, EventRegistry $eventRegistry)
    {
        $this->hydrator = $hydrator;
        $this->eventRegistry = $eventRegistry;
    }

    public function serialize(object $event, array $options = []): SerializedData
    {
        $eventName = $this->eventRegistry->eventName($event::class);

        $data = $this->hydrator->extract($event);

        $flags = JSON_THROW_ON_ERROR;

        if ($options[self::OPTION_PRETTY_PRINT] ?? false) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return new SerializedData(
                $eventName,
                json_encode($data, $flags)
            );
        } catch (JsonException $e) {
            throw new SerializationNotPossible($event, $e);
        }
    }

    public function deserialize(SerializedData $data, array $options = []): object
    {
        $class = $this->eventRegistry->eventClass($data->name);

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($data->payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DeserializationNotPossible($class, $data->payload, $e);
        }

        return $this->hydrator->hydrate($class, $payload);
    }

    /**
     * @param non-empty-list<string> $paths
     */
    public static function createDefault(array $paths): static
    {
        return new self(
            new EventHydrator(new AttributeEventMetadataFactory()),
            (new AttributeEventRegistryFactory())->create($paths)
        );
    }
}
