<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;

final class DefaultEventSerializer implements EventSerializer
{
    public const CONTEXT_EVENT_NAME = 'event_name';
    public const CONTEXT_EVENT_CLASS = 'event_class';

    private Hydrator $hydrator;

    public function __construct(
        private EventRegistry $eventRegistry,
        Hydrator|null $hydrator = null,
        private Encoder $encoder = new JsonEncoder(),
    ) {
        $this->hydrator = $hydrator ?? self::defaultHydrator();
    }

    /** @param array<string, mixed> $options */
    public function serialize(object $event, array $options = []): SerializedEvent
    {
        $name = $this->eventRegistry->eventName($event::class);

        /** @var array<string, mixed> $data */
        $data = $this->hydrator->extract($event, [
            self::CONTEXT_EVENT_NAME => $name,
            self::CONTEXT_EVENT_CLASS => $event::class,
        ]);

        return new SerializedEvent(
            $name,
            $this->encoder->encode($data, $options),
        );
    }

    /** @param array<string, mixed> $options */
    public function deserialize(SerializedEvent $data, array $options = []): object
    {
        $payload = $this->encoder->decode($data->payload, $options);
        $class = $this->eventRegistry->eventClass($data->name);

        return $this->hydrator->hydrate($class, $payload, [
            self::CONTEXT_EVENT_NAME => $data->name,
            self::CONTEXT_EVENT_CLASS => $class,
        ]);
    }

    /** @param list<string> $paths */
    public static function createFromPaths(
        array $paths,
        Hydrator|null $hydrator = null,
    ): static {
        return new self(
            (new AttributeEventRegistryFactory())->create($paths),
            $hydrator,
            new JsonEncoder(),
        );
    }

    private static function defaultHydrator(): Hydrator
    {
        return (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->build();
    }
}
