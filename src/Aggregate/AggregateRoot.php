<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use ReflectionClass;

use function array_key_exists;
use function method_exists;

abstract class AggregateRoot
{
    /** @var array<class-string<self>, AggregateRootMetadata> */
    private static array $metadata = [];

    /** @var array<AggregateChanged> */
    private array $uncommittedEvents = [];

    /** @internal */
    protected int $playhead = 0;

    final protected function __construct()
    {
    }

    abstract public function aggregateRootId(): string;

    protected function apply(AggregateChanged $event): void
    {
        $metadata = self::metadata();

        if (!array_key_exists($event::class, $metadata->applyMethods)) {
            if (!$metadata->suppressAll && !array_key_exists($event::class, $metadata->suppressEvents)) {
                throw new ApplyAttributeNotFound($this, $event);
            }

            return;
        }

        $method = $metadata->applyMethods[$event::class];

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    /**
     * @param AggregateChanged<array<string, mixed>> $event
     */
    final protected function record(AggregateChanged $event): void
    {
        $this->playhead++;

        $event = $event->recordNow($this->playhead);
        $this->uncommittedEvents[] = $event;

        $this->apply($event);
    }

    /**
     * @return array<AggregateChanged>
     */
    final public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param array<AggregateChanged> $stream
     */
    final public static function createFromEventStream(array $stream): static
    {
        $self = new static();

        foreach ($stream as $message) {
            $self->playhead++;

            if ($self->playhead !== $message->playhead()) {
                throw new PlayheadSequenceMismatch();
            }

            $self->apply($message);
        }

        return $self;
    }

    final public function playhead(): int
    {
        return $this->playhead;
    }

    private static function metadata(): AggregateRootMetadata
    {
        if (array_key_exists(static::class, self::$metadata)) {
            return self::$metadata[static::class];
        }

        $metadata = new AggregateRootMetadata();

        $reflector = new ReflectionClass(static::class);
        $attributes = $reflector->getAttributes(SuppressMissingApply::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll()) {
                $metadata->suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents() as $event) {
                $metadata->suppressEvents[$event] = true;
            }
        }

        $methods = $reflector->getMethods();

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->aggregateChangedClass();

                if (array_key_exists($eventClass, $metadata->applyMethods)) {
                    throw new DuplicateApplyMethod(
                        self::class,
                        $eventClass,
                        $metadata->applyMethods[$eventClass],
                        $method->getName()
                    );
                }

                $metadata->applyMethods[$eventClass] = $method->getName();
            }
        }

        return $metadata;
    }
}
