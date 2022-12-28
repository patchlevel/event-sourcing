<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container;

final class SendEmailMock
{
    private static int $count = 0;

    public static function send(): void
    {
        self::$count++;
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }
}
