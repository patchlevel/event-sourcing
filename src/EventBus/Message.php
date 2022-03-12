<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class Message
{
    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;

    private string $aggregateId;

    private int $playhead;

    /** @var AggregateChanged<array<string, mixed>> */
    private AggregateChanged $event;

    private DateTimeImmutable $recordedOn;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function __construct(
        string $aggregateClass,
        string $aggregateId,
        int $playhead,
        AggregateChanged $aggregateChanged,
        ?DateTimeImmutable $recordedOn = null
    ) {
        $this->aggregateClass = $aggregateClass;
        $this->aggregateId = $aggregateId;
        $this->playhead = $playhead;
        $this->event = $aggregateChanged;
        $this->recordedOn = $recordedOn ?? Clock::createDateTimeImmutable();
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function playhead(): int
    {
        return $this->playhead;
    }

    public function event(): AggregateChanged
    {
        return $this->event;
    }

    public function recordedOn(): DateTimeImmutable
    {
        return $this->recordedOn;
    }

    /**
     * @param array{aggregate_class: class-string<AggregateRoot>, aggregate_id: string, playhead: int, event: class-string<AggregateChanged<array<string, mixed>>>, payload: string, recorded_on: DateTimeImmutable} $data
     */
    final public static function deserialize(array $data): self
    {
        $class = $data['event'];

        /** @var array<string, mixed> $payload */
        $payload = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);

        $event = new $class($payload);

        return new Message(
            $data['aggregate_class'],
            $data['aggregate_id'],
            $data['playhead'],
            $event,
            $data['recorded_on']
        );
    }

    /**
     * @return array{aggregate_class: class-string<AggregateRoot>, aggregate_id: string, playhead: int, event: class-string<AggregateChanged<array<string, mixed>>>, payload: string, recorded_on: DateTimeImmutable}
     */
    final public function serialize(): array
    {
        $event = $this->event;

        return [
            'aggregate_class' => $this->aggregateClass,
            'aggregate_id' => $this->aggregateId,
            'playhead' => $this->playhead,
            'event' => $event::class,
            'payload' => json_encode($event->payload(), JSON_THROW_ON_ERROR),
            'recorded_on' => $this->recordedOn,
        ];
    }
}
