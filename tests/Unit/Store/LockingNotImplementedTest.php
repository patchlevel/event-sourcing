<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Patchlevel\EventSourcing\Store\LockingNotImplemented;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(LockingNotImplemented::class)]
final class LockingNotImplementedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new LockingNotImplemented(SQLitePlatform::class);

        self::assertSame(
            sprintf('Locking is not implemented on platform %s. Disable locking in the store options.', SQLitePlatform::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
