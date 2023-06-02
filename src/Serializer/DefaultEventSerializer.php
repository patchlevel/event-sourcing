<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;

final class DefaultEventSerializer implements EventSerializer
{
    public function __construct(
        private EventRegistry $eventRegistry,
        private Hydrator $hydrator,
        private Encoder $encoder,
        private Upcaster|null $upcaster = null,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function serialize(object $event, array $options = []): SerializedEvent
    {
        $name = $this->eventRegistry->eventName($event::class);
        $data = $this->hydrator->extract($event);

        return new SerializedEvent(
            $name,
            $this->encoder->encode($data, $options),
        );
    }

    /** @param array<string, mixed> $options */
    public function deserialize(SerializedEvent $data, array $options = []): object
    {
        $payload = $this->encoder->decode($data->payload, $options);

        $eventName = $data->name;
        if ($this->upcaster) {
            $upcast = ($this->upcaster)(new Upcast($data->name, $payload));
            $eventName = $upcast->eventName;
            $payload = $upcast->payload;
        }

        $class = $this->eventRegistry->eventClass($eventName);

        return $this->hydrator->hydrate($class, $payload);
    }

    /** @param list<string> $paths */
    public static function createFromPaths(array $paths, Upcaster|null $upcaster = null): static
    {
        return new self(
            (new AttributeEventRegistryFactory())->create($paths),
            new MetadataHydrator(),
            new JsonEncoder(),
            $upcaster,
        );
    }
}
