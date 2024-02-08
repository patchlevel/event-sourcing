<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;

use function array_key_exists;

/**
 * @template-covariant T of object
 * @psalm-immutable
 * @psalm-type Headers = array{
 *     aggregateName?: string,
 *     aggregateId?: string,
 *     playhead?: positive-int,
 *     recordedOn?: DateTimeImmutable,
 *     newStreamStart?: bool,
 *     archived?: bool,
 *     ...<string, mixed>
 *  }
 * @phpstan-type Headers = array{
 *      aggregateName?: string,
 *      aggregateId?: string,
 *      playhead?: positive-int,
 *      recordedOn?: DateTimeImmutable,
 *      newStreamStart?: bool,
 *      archived?: bool
 *   }
 */
final class Message
{
    public const HEADER_AGGREGATE_NAME = 'aggregateName';
    public const HEADER_AGGREGATE_ID = 'aggregateId';
    public const HEADER_PLAYHEAD = 'playhead';
    public const HEADER_RECORDED_ON = 'recordedOn';
    public const HEADER_ARCHIVED = 'archived';
    public const HEADER_NEW_STREAM_START = 'newStreamStart';

    /** @var Headers */
    private array $headers = [];

    /** @param T $event */
    public function __construct(
        private readonly object $event,
    ) {
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

    /** @param Headers $headers */
    public static function createWithHeaders(object $event, array $headers): self
    {
        return self::create($event)->withHeaders($headers);
    }

    /** @return T */
    public function event(): object
    {
        return $this->event;
    }

    /** @throws HeaderNotFound */
    public function aggregateName(): string
    {
        $value = $this->headers[self::HEADER_AGGREGATE_NAME] ?? null;

        if ($value === null) {
            throw HeaderNotFound::aggregateName();
        }

        return $value;
    }

    public function withAggregateName(string $value): self
    {
        return $this->withHeader(self::HEADER_AGGREGATE_NAME, $value);
    }

    /** @throws HeaderNotFound */
    public function aggregateId(): string
    {
        $value = $this->headers[self::HEADER_AGGREGATE_ID] ?? null;

        if ($value === null) {
            throw HeaderNotFound::aggregateId();
        }

        return $value;
    }

    public function withAggregateId(string $value): self
    {
        return $this->withHeader(self::HEADER_AGGREGATE_ID, $value);
    }

    /**
     * @return positive-int
     *
     * @throws HeaderNotFound
     */
    public function playhead(): int
    {
        $value = $this->headers[self::HEADER_PLAYHEAD] ?? null;

        if ($value === null) {
            throw HeaderNotFound::playhead();
        }

        return $value;
    }

    /** @param positive-int $value */
    public function withPlayhead(int $value): self
    {
        return $this->withHeader(self::HEADER_PLAYHEAD, $value);
    }

    /** @throws HeaderNotFound */
    public function recordedOn(): DateTimeImmutable
    {
        $value = $this->headers[self::HEADER_RECORDED_ON] ?? null;

        if ($value === null) {
            throw HeaderNotFound::recordedOn();
        }

        return $value;
    }

    public function withRecordedOn(DateTimeImmutable $value): self
    {
        return $this->withHeader(self::HEADER_RECORDED_ON, $value);
    }

    public function newStreamStart(): bool
    {
        $value = $this->headers[self::HEADER_NEW_STREAM_START] ?? null;

        if ($value === null) {
            throw HeaderNotFound::newStreamStart();
        }

        return $value;
    }

    public function withNewStreamStart(bool $value): self
    {
        return $this->withHeader(self::HEADER_NEW_STREAM_START, $value);
    }

    public function archived(): bool
    {
        $value = $this->headers[self::HEADER_ARCHIVED] ?? null;

        if ($value === null) {
            throw HeaderNotFound::archived();
        }

        return $value;
    }

    public function withArchived(bool $value): self
    {
        return $this->withHeader(self::HEADER_ARCHIVED, $value);
    }

    /** @throws HeaderNotFound */
    public function header(string $name): mixed
    {
        if (!array_key_exists($name, $this->headers)) {
            throw HeaderNotFound::custom($name);
        }

        return $this->headers[$name];
    }

    public function withHeader(string $name, mixed $value): self
    {
        $message = clone $this;
        $message->headers[$name] = $value;

        return $message;
    }

    /** @return Headers */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @param Headers $headers */
    public function withHeaders(array $headers): self
    {
        $message = clone $this;
        $message->headers += $headers;

        return $message;
    }

    /** @return array<string, mixed> */
    public function customHeaders(): array
    {
        $headers = $this->headers;

        unset(
            $headers[self::HEADER_AGGREGATE_NAME],
            $headers[self::HEADER_AGGREGATE_ID],
            $headers[self::HEADER_PLAYHEAD],
            $headers[self::HEADER_RECORDED_ON],
            $headers[self::HEADER_ARCHIVED],
            $headers[self::HEADER_NEW_STREAM_START],
        );

        return $headers;
    }
}
