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

    /**
     * @var array<class-string<Header>, Header>
     */
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

    /**
     * @template H of Header
     * @param array<class-string<H>, H> $headers
     */
    public static function createWithHeaders(object $event, array $headers): self
    {
        return self::create($event)->withHeaders($headers);
    }

    /** @return T */
    public function event(): object
    {
        return $this->event;
    }

    /**
     * @param class-string<H> $name
     *
     * @return H
     *
     * @throws HeaderNotFound
     *
     * @template H of Header
     */
    public function header(string $name): mixed
    {
        if (!array_key_exists($name, $this->headers)) {
            throw HeaderNotFound::custom($name);
        }

        return $this->headers[$name];
    }

    public function withHeader(Header $header): self
    {
        $message = clone $this;
        $message->headers[$header::class] = $header;

        return $message;
    }

    /**
     * @template H of Header
     * @return array<class-string<H>, H>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @template H of Header
     * @param array<class-string<H>, H> $headers
     */
    public function withHeaders(array $headers): self
    {
        $message = clone $this;
        $message->headers += $headers;

        return $message;
    }
}
