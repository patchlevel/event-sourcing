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
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;

final class DefaultEventSerializer implements EventSerializer
{
    private EventRegistry $eventRegistry;
    private EventHydrator $hydrator;
    private Encoder $encoder;
    private Upcaster $upcaster;

    public function __construct(EventRegistry $eventRegistry, EventHydrator $hydrator, Encoder $encoder, Upcaster $upcaster)
    {
        $this->eventRegistry = $eventRegistry;
        $this->hydrator = $hydrator;
        $this->encoder = $encoder;
        $this->upcaster = $upcaster;
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
        $payload = $this->encoder->decode($data->payload, $options);
        $class = $this->eventRegistry->eventClass($data->name);

        $upcast = ($this->upcaster)(new Upcast($class, $payload));

        return $this->hydrator->hydrate($upcast->class, $upcast->payload);
    }

    /**
     * @param list<string> $paths
     */
    public static function createFromPaths(array $paths): static
    {
        return new self(
            (new AttributeEventRegistryFactory())->create($paths),
            new MetadataEventHydrator(new AttributeEventMetadataFactory()),
            new JsonEncoder(),
            new UpcasterChain([])
        );
    }
}
