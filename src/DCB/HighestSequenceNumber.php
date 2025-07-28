<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
final class HighestSequenceNumber
{
    public function __construct(
        public readonly int $value,
    ) {
    }

    public static function none(): self
    {
        return new self(0);
    }

    public function isNone(): bool
    {
        return $this->value === 0;
    }
}
