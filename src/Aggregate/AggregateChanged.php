<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * @template-covariant T of array<string, mixed>
 */
abstract class AggregateChanged
{
    /** @readonly */
    protected string $aggregateId;

    /**
     * @readonly
     * @var T
     */
    protected array $payload;
    private ?int $playhead;
    private ?DateTimeImmutable $recordedOn;

    /**
     * @param T $payload
     */
    final public function __construct(string $aggregateId, array $payload = [])
    {
        $this->aggregateId = $aggregateId;
        $this->payload = $payload;
        $this->playhead = null;
        $this->recordedOn = null;
    }

    final public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    final public function playhead(): ?int
    {
        return $this->playhead;
    }

    final public function recordedOn(): ?DateTimeImmutable
    {
        return $this->recordedOn;
    }

    /**
     * @return T
     */
    final public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @internal
     */
    final public function recordNow(int $playhead): static
    {
        if ($this->playhead !== null) {
            throw new AggregateChangeRecordedAlready();
        }

        /** @psalm-suppress UnsafeGenericInstantiation */
        $event = new static($this->aggregateId, $this->payload);
        $event->playhead = $playhead;
        $event->recordedOn = $this->createRecordDate();

        return $event;
    }

    /**
     * @param array{aggregate_id: string, playhead: int, event: class-string<E>, payload: string, recorded_on: DateTimeImmutable} $data
     *
     * @return E
     *
     * @template E of self
     */
    final public static function deserialize(array $data): self
    {
        $class = $data['event'];

        /** @var array<string, mixed> $payload */
        $payload = json_decode($data['payload'], true, 512, JSON_THROW_ON_ERROR);

        $event = new $class($data['aggregate_id'], $payload);
        $event->playhead = $data['playhead'];
        $event->recordedOn = $data['recorded_on'];

        return $event;
    }

    /**
     * @return array{aggregate_id: string, playhead: int, event: class-string<self>, payload: string, recorded_on: DateTimeImmutable}
     */
    final public function serialize(): array
    {
        $recordedOn = $this->recordedOn;
        $playhead = $this->playhead;

        if ($recordedOn === null || $playhead === null) {
            throw new AggregateChangeNotRecorded();
        }

        return [
            'aggregate_id' => $this->aggregateId,
            'playhead' => $playhead,
            'event' => static::class,
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'recorded_on' => $recordedOn,
        ];
    }

    protected function createRecordDate(): DateTimeImmutable
    {
        return Clock::createDateTimeImmutable();
    }
}
