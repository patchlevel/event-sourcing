<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock;

final class Message
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
}
