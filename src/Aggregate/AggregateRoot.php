<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;

use function array_key_exists;

abstract class AggregateRoot
{
    private static ?AggregateRootMetadataFactory $metadataFactory = null;

    /** @var list<object> */
    private array $uncommittedEvents = [];

    private int $playhead = 0;

    final protected function __construct()
    {
    }

    abstract public function aggregateRootId(): string;

    protected function apply(object $event): void
    {
        $metadata = self::metadata();

        if (!array_key_exists($event::class, $metadata->applyMethods)) {
            if (!$metadata->suppressAll && !array_key_exists($event::class, $metadata->suppressEvents)) {
                throw new ApplyMethodNotFound($this::class, $event::class);
            }

            return;
        }

        $method = $metadata->applyMethods[$event::class];
        $this->$method($event);
    }

    final protected function recordThat(object $event): void
    {
        $this->playhead++;

        $this->apply($event);

        $this->uncommittedEvents[] = $event;
    }

    /**
     * @internal
     *
     * @param list<object> $events
     */
    final public function catchUp(array $events): void
    {
        foreach ($events as $event) {
            $this->playhead++;
            $this->apply($event);
        }
    }

    /**
     * @return list<object>
     */
    final public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param list<object> $events
     */
    final public static function createFromEvents(array $events): static
    {
        $self = new static();
        $self->catchUp($events);

        return $self;
    }

    final public function playhead(): int
    {
        return $this->playhead;
    }

    final public static function metadata(): AggregateRootMetadata
    {
        if (static::class === self::class) {
            throw new MetadataNotPossible();
        }

        if (!self::$metadataFactory) {
            self::$metadataFactory = new AttributeAggregateRootMetadataFactory();
        }

        return self::$metadataFactory->metadata(static::class);
    }

    final public static function setMetadataFactory(AggregateRootMetadataFactory $metadataFactory): void
    {
        self::$metadataFactory = $metadataFactory;
    }
}
