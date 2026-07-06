<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container;

use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Container\Container;
use Patchlevel\EventSourcing\Container\ServiceCreationFailed;
use Patchlevel\EventSourcing\Container\ServiceNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\ArrayContainer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testGetFallsBackToExternalContainer(): void
    {
        $externalService = new stdClass();
        $container = new Container(externalContainer: new ArrayContainer(['app.service' => $externalService]));

        self::assertSame($externalService, $container->get('app.service'));
    }

    public function testLocalServiceWinsOverExternalContainer(): void
    {
        $localService = new stdClass();
        $container = new Container(
            services: ['app.service' => $localService],
            externalContainer: new ArrayContainer(['app.service' => new stdClass()]),
        );

        self::assertSame($localService, $container->get('app.service'));
    }

    public function testHasConsultsExternalContainer(): void
    {
        $container = new Container(externalContainer: new ArrayContainer(['app.service' => new stdClass()]));

        self::assertTrue($container->has('app.service'));
        self::assertFalse($container->has('app.unknown'));
    }

    public function testServiceNotFoundWhenMissingEverywhere(): void
    {
        $container = new Container(externalContainer: new ArrayContainer());

        $this->expectException(ServiceNotFound::class);
        $container->get('app.unknown');
    }

    public function testServiceNotFoundWithoutExternalContainer(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFound::class);
        $container->get('app.unknown');
    }

    public function testNonObjectExternalServiceFails(): void
    {
        $container = new Container(externalContainer: new ArrayContainer(['app.parameter' => 'a-string']));

        $this->expectException(ServiceCreationFailed::class);
        $container->get('app.parameter');
    }

    public function testAliasResolvesIntoExternalContainer(): void
    {
        $externalService = new SystemClock();
        $container = new Container(externalContainer: new ArrayContainer(['app.clock' => $externalService]));
        $container->alias('clock', 'app.clock');

        self::assertTrue($container->has('clock'));
        self::assertSame($externalService, $container->get('clock'));
    }

    public function testExternalContainerIsResolvedLazily(): void
    {
        $external = new ArrayContainer(['app.service' => new stdClass()]);
        $container = new Container(externalContainer: $external);
        $container->bind('local', static fn (): stdClass => new stdClass());

        $container->get('local');

        self::assertSame([], $external->resolvedIds);

        $container->get('app.service');

        self::assertSame(['app.service'], $external->resolvedIds);
    }
}
