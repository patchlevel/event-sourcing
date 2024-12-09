<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\ServiceLocator;
use Patchlevel\EventSourcing\CommandBus\ServiceNotFound;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/** @covers \Patchlevel\EventSourcing\CommandBus\ServiceLocator */
class ServiceLocatorTest extends TestCase
{
    public function testGetService(): void
    {
        $service = new SystemClock();

        $serviceLocator = new ServiceLocator([
            'clock' => $service,
            ClockInterface::class => $service,
        ]);

        self::assertSame($service, $serviceLocator->get('clock'));
        self::assertSame($service, $serviceLocator->get(ClockInterface::class));
    }

    public function testNotFound(): void
    {
        $service = new SystemClock();

        $serviceLocator = new ServiceLocator([
            'clock' => $service,
            ClockInterface::class => $service,
        ]);

        $this->expectException(ServiceNotFound::class);

        $serviceLocator->get('foo');
    }

    public function testHasService(): void
    {
        $service = new SystemClock();

        $serviceLocator = new ServiceLocator([
            'clock' => $service,
            ClockInterface::class => $service,
        ]);

        self::assertTrue($serviceLocator->has('clock'));
        self::assertTrue($serviceLocator->has(ClockInterface::class));
        self::assertFalse($serviceLocator->has('foo'));
    }
}
