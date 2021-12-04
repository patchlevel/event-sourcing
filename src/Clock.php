<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing;

use DateTimeImmutable;

use function sleep;
use function sprintf;

final class Clock
{
    private static ?DateTimeImmutable $frozenDateTime = null;

    public static function freeze(DateTimeImmutable $frozenDateTime): void
    {
        self::$frozenDateTime = $frozenDateTime;
    }

    /**
     * @param positive-int $seconds
     */
    public static function sleep(int $seconds): void
    {
        if (self::$frozenDateTime instanceof DateTimeImmutable) {
            self::$frozenDateTime = self::$frozenDateTime->modify(sprintf('+%s seconds', $seconds));

            return;
        }

        sleep($seconds);
    }

    public static function createDateTimeImmutable(): DateTimeImmutable
    {
        return self::$frozenDateTime ?: new DateTimeImmutable();
    }

    public static function reset(): void
    {
        self::$frozenDateTime = null;
    }
}
