<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\Hydrator\EventHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataEventHydrator;

final class DefaultEventSerializer implements EventSerializer
{
    private EventRegistry $eventRegistry;
    private EventHydrator $hydrator;
    private Encoder $encoder;

    public function __construct(EventRegistry $eventRegistry, EventHydrator $hydrator, Encoder $encoder)
    {
        $this->eventRegistry = $eventRegistry;
        $this->hydrator = $hydrator;
        $this->encoder = $encoder;
    }

    public function serialize(object $event, array $options = []): SerializedEvent
    {
        $name = $this->eventRegistry->eventName($event::class);
        $data = $this->hydrator->extract($event);

        return new SerializedEvent(
            $name,
            $this->encoder->encode($data, $options)
        );
    }

    public function deserialize(SerializedEvent $data, array $options = []): object
    {
        $class = $this->eventRegistry->eventClass($data->name);
        $payload = $this->encoder->decode($data->payload, $options);

        return $this->hydrator->hydrate($class, $payload);
    }

    /**
     * @param list<string> $paths
     */
    public static function createFromPaths(array $paths): static
    {
        return new self(
            (new AttributeEventRegistryFactory())->create($paths),
            new MetadataEventHydrator(new AttributeEventMetadataFactory()),
            new JsonEncoder()
        );
    }
}
