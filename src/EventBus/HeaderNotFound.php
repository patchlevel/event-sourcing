<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function sprintf;

final class HeaderNotFound extends EventBusException
{
    private function __construct(
        public readonly string $name,
    ) {
        parent::__construct(sprintf('message header "%s" is not defined', $name));
    }

    public static function aggregateName(): self
    {
        return new self(Message::HEADER_AGGREGATE_NAME);
    }

    public static function aggregateId(): self
    {
        return new self(Message::HEADER_AGGREGATE_ID);
    }

    public static function playhead(): self
    {
        return new self(Message::HEADER_PLAYHEAD);
    }

    public static function recordedOn(): self
    {
        return new self(Message::HEADER_RECORDED_ON);
    }

    public static function newStreamStart(): self
    {
        return new self(Message::HEADER_NEW_STREAM_START);
    }

    public static function archived(): self
    {
        return new self(Message::HEADER_ARCHIVED);
    }

    public static function custom(string $name): self
    {
        return new self($name);
    }
}
