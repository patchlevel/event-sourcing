<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\LockCouldNotBeFreed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockCouldNotBeFreed::class)]
final class LockCouldNotBeFreedTest extends TestCase
{
    public function testNotExist(): void
    {
        $exception = LockCouldNotBeFreed::notExist(133742);

        self::assertSame(
            'The lock with id [133742] could not be freed as it does not exist',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testNotOurs(): void
    {
        $exception = LockCouldNotBeFreed::notOurs(133742);

        self::assertSame(
            'The lock with id [133742] could not be freed as it is not ours',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
