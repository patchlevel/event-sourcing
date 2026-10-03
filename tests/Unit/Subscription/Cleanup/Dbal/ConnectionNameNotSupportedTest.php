<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\ConnectionNameNotSupported;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionNameNotSupported::class)]
final class ConnectionNameNotSupportedTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ConnectionNameNotSupported('foo');

        self::assertSame(
            'Connection name "foo" is not supported. Only a single connection is available. Please use the connection registry.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
