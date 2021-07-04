<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

abstract class AggregateChanged
{
    protected string $aggregateId;

    /** @var array<string, mixed> */
    protected array $payload;
    private ?int $playhead;
    private ?DateTimeImmutable $recordedOn;

    /**
     * @param array<string, mixed> $payload
     */
    final private function __construct(string $aggregateId, array $payload = [])
    {
        $this->aggregateId = $aggregateId;
        $this->payload = $payload;
        $this->playhead = null;
        $this->recordedOn = null;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function playhead(): ?int
    {
        return $this->playhead;
    }

    public function recordedOn(): ?DateTimeImmutable
    {
        return $this->recordedOn;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return static
     */
    protected static function occur(string $aggregateId, array $payload = []): self
    {
        return new static($aggregateId, $payload);
    }

    public function recordNow(int $playhead): self
    {
        if ($this->playhead !== null) {
            throw new AggregateException('Event has already been recorded.');
        }

        $event = new static($this->aggregateId, $this->payload);
        $event->playhead = $playhead;
        $event->recordedOn = $this->createRecordDate();

        return $event;
    }

    /**
     * @param array{aggregateId: string, playhead: int, event: class-string<self>, payload: string, recordedOn: DateTimeImmutable|null} $data
     */
    public static function deserialize(array $data): self
    {
        $class = $data['event'];

        /** @var array<string, mixed> $payload */
        $payload = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);

        $event = new $class($data['aggregateId'], $payload);
        $event->playhead = $data['playhead'];
        $event->recordedOn = $data['recordedOn'];

        return $event;
    }

    /**
     * @return array{aggregateId: string, playhead: int, event: class-string<self>, payload: string, recordedOn: DateTimeImmutable}
     */
    public function serialize(): array
    {
        $recordedOn = $this->recordedOn;
        $playhead = $this->playhead;

        if ($recordedOn === null || $playhead === null) {
            throw new AggregateException('The change was not recorded.');
        }

        return [
            'aggregateId' => $this->aggregateId,
            'playhead' => $playhead,
            'event' => static::class,
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'recordedOn' => $recordedOn,
        ];
    }

    protected function createRecordDate(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
