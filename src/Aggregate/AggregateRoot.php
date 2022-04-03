<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;

use function array_key_exists;

abstract class AggregateRoot
{
    private static ?AggregateRootMetadataFactory $metadataFactory = null;

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
                throw new PlayheadSequenceMismatch(static::class);
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
        if (!self::$metadataFactory) {
            self::$metadataFactory = new AttributeAggregateRootMetadataFactory();
        }

        return self::$metadataFactory->metadata(static::class);
    }
}
