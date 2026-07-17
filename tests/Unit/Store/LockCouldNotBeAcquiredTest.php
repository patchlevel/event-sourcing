<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\LockCouldNotBeAcquired;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockCouldNotBeAcquired::class)]
final class LockCouldNotBeAcquiredTest extends TestCase
{
    public function testByTimeout(): void
    {
        $exception = LockCouldNotBeAcquired::byTimeout(133742, 10);

        self::assertSame(
            'The lock with id [133742] could not be acquired with a timeout of 10',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testByError(): void
    {
        $exception = LockCouldNotBeAcquired::byError(133742);

        self::assertSame(
            'There was an error when tried to get the lock with id [133742]',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
