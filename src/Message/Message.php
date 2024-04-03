<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use function array_key_exists;
use function array_values;
use function assert;
use function is_a;

/**
 * @template-covariant T of object
 * @psalm-immutable
 */
final class Message
{
    /** @var array<class-string, object> */
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

    /** @param iterable<object> $headers */
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
     * @template H1 of object
     */
    public function header(string $name): object
    {
        if (!array_key_exists($name, $this->headers)) {
            throw new HeaderNotFound($name);
        }

        $header = $this->headers[$name];

        assert(is_a($header, $name, true));

        return $header;
    }

    /** @param class-string $name */
    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    public function withHeader(object $header): self
    {
        $message = clone $this;
        $message->headers[$header::class] = $header;

        return $message;
    }

    /** @return list<object> */
    public function headers(): array
    {
        return array_values($this->headers);
    }

    /** @param iterable<object> $headers */
    public function withHeaders(iterable $headers): self
    {
        $message = clone $this;

        foreach ($headers as $header) {
            $message->headers[$header::class] = $header;
        }

        return $message;
    }
}
