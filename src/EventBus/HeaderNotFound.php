<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function sprintf;

final class HeaderNotFound extends EventBusException
{
    private function __construct(string $name)
    {
        parent::__construct(sprintf('message header "%s" is not defined', $name));
    }

    public static function aggregateClass(): self
    {
        return new self(Message::HEADER_AGGREGATE_CLASS);
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

    public static function custom(string $name): self
    {
        return new self($name);
    }
}
