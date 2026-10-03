<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\UnexpectedConnectionType;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UnexpectedConnectionType::class)]
final class UnexpectedConnectionTypeTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new UnexpectedConnectionType('foo', Connection::class, PDO::class);

        self::assertSame(
            sprintf('Expected connection "foo" to be of type "%s", got "%s"', Connection::class, PDO::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testCreateWithNullConnectionName(): void
    {
        $exception = new UnexpectedConnectionType(null, Connection::class, PDO::class);

        self::assertSame(
            sprintf('Expected connection "default (null)" to be of type "%s", got "%s"', Connection::class, PDO::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
