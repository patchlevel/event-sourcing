<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

use DateTimeInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidInterface;

use function sprintf;
use function str_pad;

use const STR_PAD_LEFT;

final class IncrementalRamseyUuidFactory extends UuidFactory
{
    private int $counter = 0;

    public function uuid7(DateTimeInterface|null $dateTime = null): UuidInterface
    {
        $number = ++$this->counter;

        $string = sprintf(
            '10000000-7000-0000-0000-%s',
            str_pad((string)$number, 12, '0', STR_PAD_LEFT),
        );

        return Uuid::fromString($string);
    }

    public function reset(): void
    {
        $this->counter = 0;
    }
}
