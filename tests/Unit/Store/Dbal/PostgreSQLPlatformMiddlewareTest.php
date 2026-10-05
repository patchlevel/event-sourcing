<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform as BasePostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatform;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatformMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSQLPlatformMiddleware::class)]
final class PostgreSQLPlatformMiddlewareTest extends TestCase
{
    public function testReplacePostgreSQLPlatform(): void
    {
        $versionProvider = $this->createStub(ServerVersionProvider::class);

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('getDatabasePlatform')
            ->with($versionProvider)
            ->willReturn(new BasePostgreSQLPlatform());

        $wrappedDriver = (new PostgreSQLPlatformMiddleware())->wrap($driver);

        self::assertInstanceOf(PostgreSQLPlatform::class, $wrappedDriver->getDatabasePlatform($versionProvider));
    }

    public function testKeepOtherPlatforms(): void
    {
        $versionProvider = $this->createStub(ServerVersionProvider::class);
        $platform = new MySQLPlatform();

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('getDatabasePlatform')
            ->with($versionProvider)
            ->willReturn($platform);

        $wrappedDriver = (new PostgreSQLPlatformMiddleware())->wrap($driver);

        self::assertSame($platform, $wrappedDriver->getDatabasePlatform($versionProvider));
    }
}
