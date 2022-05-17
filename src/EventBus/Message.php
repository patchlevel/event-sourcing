<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function array_keys;

/**
 * @psalm-immutable
 */
final class Message
{
    public const HEADER_AGGREGATE_CLASS = 'aggregateClass';
    public const HEADER_AGGREGATE_ID = 'aggregateId';
    public const HEADER_PLAYHEAD = 'playhead';
    public const HEADER_RECORDED_ON = 'recordedOn';

    /** @var class-string<AggregateRoot>|null */
    private ?string $aggregateClass = null;
    private ?string $aggregateId = null;
    private ?int $playhead = null;
    private ?DateTimeImmutable $recordedOn = null;

    /** @var array<string, mixed> */
    private array $customHeaders = [];

    private readonly object $event;

    public function __construct(object $event)
    {
        $this->event = $event;
    }

    public static function create(object $event): self
    {
        return new self($event);
    }

    public function event(): object
    {
        return $this->event;
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        $value = $this->aggregateClass;

        if ($value === null) {
            throw HeaderNotFound::aggregateClass();
        }

        return $value;
    }

    /**
     * @param class-string<AggregateRoot> $value
     */
    public function withAggregateClass(string $value): self
    {
        $message = clone $this;
        $message->aggregateClass = $value;

        return $message;
    }

    public function aggregateId(): string
    {
        $value = $this->aggregateId;

        if ($value === null) {
            throw HeaderNotFound::aggregateId();
        }

        return $value;
    }

    public function withAggregateId(string $value): self
    {
        $message = clone $this;
        $message->aggregateId = $value;

        return $message;
    }

    public function playhead(): int
    {
        $value = $this->playhead;

        if ($value === null) {
            throw HeaderNotFound::playhead();
        }

        return $value;
    }

    public function withPlayhead(int $value): self
    {
        $message = clone $this;
        $message->playhead = $value;

        return $message;
    }

    public function recordedOn(): DateTimeImmutable
    {
        $value = $this->recordedOn;

        if ($value === null) {
            throw HeaderNotFound::recordedOn();
        }

        return $value;
    }

    public function withRecordedOn(DateTimeImmutable $value): self
    {
        $message = clone $this;
        $message->recordedOn = $value;

        return $message;
    }

    public function customHeader(string $name): mixed
    {
        if (array_keys($this->customHeaders, $name)) {
            throw HeaderNotFound::custom($name);
        }

        return $this->customHeaders[$name];
    }

    public function withCustomHeader(string $name, mixed $value): self
    {
        $message = clone $this;
        $message->customHeaders[$name] = $value;

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function customHeaders(): array
    {
        return $this->customHeaders;
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function withCustomHeaders(array $headers): self
    {
        $message = clone $this;
        $message->customHeaders += $headers;

        return $message;
    }
}
