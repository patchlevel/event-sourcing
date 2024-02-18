<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function array_key_exists;

/**
 * @template-covariant T of object
 * @psalm-immutable
 */
final class Message
{
    public const HEADER_AGGREGATE_NAME = 'aggregateName';
    public const HEADER_AGGREGATE_ID = 'aggregateId';
    public const HEADER_PLAYHEAD = 'playhead';
    public const HEADER_RECORDED_ON = 'recordedOn';
    public const HEADER_ARCHIVED = 'archived';
    public const HEADER_NEW_STREAM_START = 'newStreamStart';

    /** @var array<class-string<Header>, Header> */
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

    /** @param iterable<Header> $headers */
    public static function createWithHeaders(object $event, iterable $headers): self
    {
        return self::create($event)->withHeaders($headers);
    }

    /** @return T */
    public function event(): object
    {
        return $this->event;
    }

    /**
     * @param class-string<H1> $name
     *
     * @return H1
     *
     * @throws HeaderNotFound
     *
     * @template H1 of Header
     */
    public function header(string $name): Header
    {
        if (!array_key_exists($name, $this->headers)) {
            throw HeaderNotFound::custom($name);
        }

        $header = $this->headers[$name];

        assert(is_a($header, $name, true));

        return $header;
    }

    public function withHeader(Header $header): self
    {
        $message = clone $this;
        $message->headers[$header::class] = $header;

        return $message;
    }

    /** @return list<Header> */
    public function headers(): array
    {
        return array_values($this->headers);
    }

    /** @param iterable<Header> $headers */
    public function withHeaders(iterable $headers): self
    {
        $message = clone $this;

        foreach ($headers as $header) {
            $message->headers[$header::class] = $header;
        }

        return $message;
    }
}
