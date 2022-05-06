<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function array_key_exists;

/**
 * @psalm-immutable
 */
final class Message
{
    public const HEADER_AGGREGATE_CLASS = 'aggregateClass';
    public const HEADER_AGGREGATE_ID = 'aggregateId';
    public const HEADER_PLAYHEAD = 'playhead';
    public const HEADER_RECORDED_ON = 'recordedOn';

    private object $event;

    /** @var array{aggregateClass?: class-string<AggregateRoot>, aggregateId?:string, playhead?:int, recordedOn?: DateTimeImmutable} */
    private array $headers;

    /**
     * @param array{aggregateClass?: class-string<AggregateRoot>, aggregateId?:string, playhead?:int, recordedOn?: DateTimeImmutable} $headers
     */
    public function __construct(object $event, array $headers = [])
    {
        $this->event = $event;
        $this->headers = $headers;
    }

    public function event(): object
    {
        return $this->event;
    }

    /**
     * @return array{aggregateClass?: class-string<AggregateRoot>, aggregateId?:string, playhead?:int, recordedOn?: DateTimeImmutable}
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        return $this->header(self::HEADER_AGGREGATE_CLASS);
    }

    public function aggregateId(): string
    {
        return $this->header(self::HEADER_AGGREGATE_ID);
    }

    public function playhead(): int
    {
        return $this->header(self::HEADER_PLAYHEAD);
    }

    public function recordedOn(): DateTimeImmutable
    {
        return $this->header(self::HEADER_RECORDED_ON);
    }

    /**
     * @param T $name
     *
     * @template T as string
     * @psalm-param (
     *     T is self::HEADER_AGGREGATE_CLASS
     *     ? class-string<AggregateRoot>
     *     : (T is self::HEADER_AGGREGATE_ID
     *        ? string
     *        : (T is self::HEADER_PLAYHEAD
     *           ? int
     *           : (T is self::HEADER_RECORDED_ON
     *              ? DateTimeImmutable
     *              : mixed
     *             )
     *          )
     *       )
     * ) $value
     */
    public function withHeader(string $name, mixed $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @param T $name
     *
     * @template T as string
     * @psalm-return (
     *     T is self::HEADER_AGGREGATE_CLASS
     *     ? class-string<AggregateRoot>
     *     : (T is self::HEADER_AGGREGATE_ID
     *        ? string
     *        : (T is self::HEADER_PLAYHEAD
     *           ? int
     *           : (T is self::HEADER_RECORDED_ON
     *              ? DateTimeImmutable
     *              : mixed
     *             )
     *          )
     *       )
     *    )
     */
    public function header(string $name): mixed
    {
        if (!array_key_exists($name, $this->headers)) {
            throw new HeaderNotFound($name);
        }

        return $this->headers[$name];
    }
}
