<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function sprintf;

class HeaderNotFound extends EventBusException
{
    private function __construct(string $name)
    {
        parent::__construct(sprintf('message header "%s" is not defined', $name));
    }

    public static function aggregateClass(): self
    {
        return new self('aggregateClass');
    }

    public static function aggregateId(): self
    {
        return new self('aggregateClass');
    }

    public static function playhead(): self
    {
        return new self('aggregateClass');
    }

    public static function recordedOn(): self
    {
        return new self('recordedOn');
    }

    public static function custom(string $name): self
    {
        return new self($name);
    }
}
