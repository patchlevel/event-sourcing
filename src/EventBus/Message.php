<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function array_key_exists;

/**
 * @template-covariant T of object
 * @psalm-immutable
 * @psalm-type Headers = array{aggregateClass?: class-string<AggregateRoot>, aggregateId?:string, playhead?:positive-int, recordedOn?: DateTimeImmutable, newStreamStart?: bool, archived?: bool}
 */
final class Message
{
    public const HEADER_AGGREGATE_CLASS = 'aggregateClass';
    public const HEADER_AGGREGATE_ID = 'aggregateId';
    public const HEADER_PLAYHEAD = 'playhead';
    public const HEADER_RECORDED_ON = 'recordedOn';
    public const HEADER_ARCHIVED = 'archived';
    public const HEADER_NEW_STREAM_START = 'newStreamStart';

    /** @var class-string<AggregateRoot>|null */
    private string|null $aggregateClass = null;
    private string|null $aggregateId = null;
    /** @var positive-int|null  */
    private int|null $playhead = null;
    private DateTimeImmutable|null $recordedOn = null;
    private bool $newStreamStart = false;
    private bool $archived = false;

    /** @var array<string, mixed> */
    private array $customHeaders = [];

    /** @param T $event */
    public function __construct(private readonly object $event)
    {
    }

    /**
     * @param T1 $event
     *
     * @return static<T1>
     *
     * @template T1 of object
     */
    public static function create(object $event): self
    {
        return new self($event);
    }

    /** @return T */
    public function event(): object
    {
        return $this->event;
    }

    /** @return class-string<AggregateRoot> */
    public function aggregateClass(): string
    {
        $value = $this->aggregateClass;

        if ($value === null) {
            throw HeaderNotFound::aggregateClass();
        }

        return $value;
    }

    /** @param class-string<AggregateRoot> $value */
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

    /** @return positive-int */
    public function playhead(): int
    {
        $value = $this->playhead;

        if ($value === null) {
            throw HeaderNotFound::playhead();
        }

        return $value;
    }

    /** @param positive-int $value */
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

    public function newStreamStart(): bool
    {
        return $this->newStreamStart;
    }

    public function withNewStreamStart(bool $value): self
    {
        $message = clone $this;
        $message->newStreamStart = $value;

        return $message;
    }

    public function archived(): bool
    {
        return $this->archived;
    }

    public function withArchived(bool $value): self
    {
        $message = clone $this;
        $message->archived = $value;

        return $message;
    }

    public function customHeader(string $name): mixed
    {
        if (!array_key_exists($name, $this->customHeaders)) {
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

    /** @return array<string, mixed> */
    public function customHeaders(): array
    {
        return $this->customHeaders;
    }

    /** @param array<string, mixed> $headers */
    public function withCustomHeaders(array $headers): self
    {
        $message = clone $this;
        $message->customHeaders += $headers;

        return $message;
    }

    /** @return Headers */
    public function headers(): array
    {
        $headers = $this->customHeaders;

        if ($this->aggregateClass !== null) {
            $headers[self::HEADER_AGGREGATE_CLASS] = $this->aggregateClass;
        }

        if ($this->aggregateId !== null) {
            $headers[self::HEADER_AGGREGATE_ID] = $this->aggregateId;
        }

        if ($this->playhead !== null) {
            $headers[self::HEADER_PLAYHEAD] = $this->playhead;
        }

        if ($this->recordedOn !== null) {
            $headers[self::HEADER_RECORDED_ON] = $this->recordedOn;
        }

        $headers[self::HEADER_NEW_STREAM_START] = $this->newStreamStart;
        $headers[self::HEADER_ARCHIVED] = $this->archived;

        return $headers;
    }

    /** @param Headers $headers */
    public static function createWithHeaders(object $event, array $headers): self
    {
        $message = self::create($event);

        if (array_key_exists(self::HEADER_AGGREGATE_CLASS, $headers)) {
            $message = $message->withAggregateClass($headers[self::HEADER_AGGREGATE_CLASS]);
        }

        if (array_key_exists(self::HEADER_AGGREGATE_ID, $headers)) {
            $message = $message->withAggregateId($headers[self::HEADER_AGGREGATE_ID]);
        }

        if (array_key_exists(self::HEADER_PLAYHEAD, $headers)) {
            $message = $message->withPlayhead($headers[self::HEADER_PLAYHEAD]);
        }

        if (array_key_exists(self::HEADER_RECORDED_ON, $headers)) {
            $message = $message->withRecordedOn($headers[self::HEADER_RECORDED_ON]);
        }

        if (array_key_exists(self::HEADER_NEW_STREAM_START, $headers)) {
            $message = $message->withNewStreamStart($headers[self::HEADER_NEW_STREAM_START]);
        }

        if (array_key_exists(self::HEADER_ARCHIVED, $headers)) {
            $message = $message->withArchived($headers[self::HEADER_ARCHIVED]);
        }

        unset(
            $headers[self::HEADER_AGGREGATE_CLASS],
            $headers[self::HEADER_AGGREGATE_ID],
            $headers[self::HEADER_PLAYHEAD],
            $headers[self::HEADER_RECORDED_ON],
            $headers[self::HEADER_NEW_STREAM_START],
            $headers[self::HEADER_ARCHIVED],
        );

        return $message->withCustomHeaders($headers);
    }
}
