<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\ServiceNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceNotFound::class)]
final class ServiceNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ServiceNotFound('foo');

        self::assertSame('service "foo" not found', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }
}
