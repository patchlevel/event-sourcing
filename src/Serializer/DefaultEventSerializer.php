<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydrator;

use function is_array;

final class DefaultEventSerializer implements EventSerializer
{
    public function __construct(
        private EventRegistry $eventRegistry,
        private Hydrator $hydrator = new StackHydrator(),
        private Encoder $encoder = new JsonEncoder(),
    ) {
    }

    /** @param array<string, mixed> $options */
    public function serialize(object $event, array $options = []): SerializedEvent
    {
        $name = $this->eventRegistry->eventName($event::class);
        $data = $this->hydrator->extract($event);

        if (!is_array($data)) {
            throw new EventPayloadNotAnArray($event::class, $data);
        }

        /** @var array<string, mixed> $payload */
        $payload = $data;

        return new SerializedEvent(
            $name,
            $this->encoder->encode($payload, $options),
        );
    }

    /** @param array<string, mixed> $options */
    public function deserialize(SerializedEvent $data, array $options = []): object
    {
        $payload = $this->encoder->decode($data->payload, $options);
        $class = $this->eventRegistry->eventClass($data->name);

        return $this->hydrator->hydrate($class, $payload);
    }

    /** @param list<string> $paths */
    public static function createFromPaths(
        array $paths,
        Hydrator $hydrator = new StackHydrator(),
    ): static {
        return new self(
            (new AttributeEventRegistryFactory())->create($paths),
            $hydrator,
            new JsonEncoder(),
        );
    }
}
