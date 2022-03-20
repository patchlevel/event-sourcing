<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\EventSourcing\EventBus\Message;
use ReflectionClass;

use function array_key_exists;

abstract class AggregateRoot
{
    /** @var array<class-string<self>, AggregateRootMetadata> */
    private static array $metadata = [];

    /** @var list<Message> */
    private array $uncommittedMessages = [];

    /** @internal */
    protected int $playhead = 0;

    final protected function __construct()
    {
    }

    abstract public function aggregateRootId(): string;

    protected function apply(object $event): void
    {
        $metadata = self::metadata();

        if (!array_key_exists($event::class, $metadata->applyMethods)) {
            if (!$metadata->suppressAll && !array_key_exists($event::class, $metadata->suppressEvents)) {
                throw new ApplyAttributeNotFound($this, $event);
            }

            return;
        }

        $method = $metadata->applyMethods[$event::class];
        $this->$method($event);
    }

    final protected function record(object $event): void
    {
        $this->playhead++;

        $this->apply($event);

        $this->uncommittedMessages[] = new Message(
            static::class,
            $this->aggregateRootId(),
            $this->playhead,
            $event
        );
    }

    /**
     * @return list<Message>
     */
    final public function releaseMessages(): array
    {
        $messages = $this->uncommittedMessages;
        $this->uncommittedMessages = [];

        return $messages;
    }

    /**
     * @param list<Message> $messages
     */
    final public static function createFromMessages(array $messages): static
    {
        $self = new static();

        foreach ($messages as $message) {
            $self->playhead++;

            if ($self->playhead !== $message->playhead()) {
                throw new PlayheadSequenceMismatch();
            }

            $self->apply($message->event());
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
                $eventClass = $instance->eventClass();

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
