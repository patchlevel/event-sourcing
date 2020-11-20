<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use function get_class;
use function json_decode;
use function json_encode;

abstract class AggregateChanged
{
    protected string $aggregateId;

    /**
     * @var array<string, mixed>
     */
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
     * @return  array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return static
     */
    protected static function occur(string $aggregateId, array $payload = []): self
    {
        return new static($aggregateId, $payload);
    }

    public function recordNow(int $playhead): self
    {
        $event = new static($this->aggregateId, $this->payload);
        $event->playhead = $playhead;
        $event->recordedOn = new DateTimeImmutable();

        return $event;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function deserialize(array $data): self
    {
        /** @var string $class */
        $class = $data['event'];

        if (!is_subclass_of($class, self::class)) {
            throw new AggregateException();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string)$data['payload'], true, 512, JSON_THROW_ON_ERROR);

        $event = new $class((string)$data['aggregateId'], $payload);
        $event->playhead = (int)$data['playhead'];
        $event->recordedOn = $data['recordedOn'] ? new DateTimeImmutable((string)$data['recordedOn']) : null;

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'aggregateId' => $this->aggregateId,
            'playhead' => $this->playhead,
            'event' => get_class($this),
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'recordedOn' => $this->recordedOn instanceof DateTimeImmutable ? $this->recordedOn->format('Y-m-d\TH:i:s.uP') : null,
        ];
    }
}
