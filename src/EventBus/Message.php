<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function array_key_exists;
use function array_map;
use function array_values;

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
     * @var class-string-map<H of Header, H>
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
     * @param array<Header> $headers
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
     * @param class-string<H1> $name
     *
     * @return H1
     *
     * @throws HeaderNotFound
     *
     * @template H1 of Header
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
     * @return array<class-string<Header>, Header>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param array<Header> $headers
     */
    public function withHeaders(array $headers): self
    {
        $newHeaders = [];

        foreach ($headers as $header) {
            $newHeaders[$header::class] = $header;
        }

        $message = clone $this;
        $message->headers = array_merge($message->headers, $newHeaders);

        return $message;
    }
}
