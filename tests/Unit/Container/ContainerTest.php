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

    public function testBindReplacesAlias(): void
    {
        $service = new stdClass();
        $container = new Container();
        $container->bind('original', new stdClass());
        $container->alias('service', 'original');

        $container->bind('service', $service);

        self::assertSame($service, $container->get('service'));
    }

    public function testBindReplacesFactory(): void
    {
        $service = new stdClass();
        $container = new Container();
        $container->bind('service', static fn (): stdClass => new stdClass());

        $container->bind('service', $service);

        self::assertSame($service, $container->get('service'));
    }

    public function testBindInvokableObjectAsService(): void
    {
        $service = new class () {
            public function __invoke(): stdClass
            {
                return new stdClass();
            }
        };
        $container = new Container();

        $container->bind('service', $service);

        self::assertSame($service, $container->get('service'));
    }

    public function testProvidesOnlyLocalServices(): void
    {
        $container = new Container(externalContainer: new ArrayContainer(['app.service' => new stdClass()]));
        $container->bind('local', new stdClass());
        $container->alias('local.alias', 'local');
        $container->alias('external.alias', 'app.service');

        self::assertTrue($container->provides('local'));
        self::assertTrue($container->provides('local.alias'));
        self::assertFalse($container->provides('app.service'));
        self::assertFalse($container->provides('external.alias'));
        self::assertTrue($container->has('external.alias'));
    }

    public function testAliasToItselfFallsBackToExternalContainer(): void
    {
        $externalService = new SystemClock();
        $container = new Container(externalContainer: new ArrayContainer(['clock' => $externalService]));
        $container->bind('clock', new SystemClock());

        $container->alias('clock', 'clock');

        self::assertFalse($container->provides('clock'));
        self::assertSame($externalService, $container->get('clock'));
    }

    public function testCircularAliasFails(): void
    {
        $container = new Container();
        $container->alias('a', 'b');
        $container->alias('b', 'a');

        $this->expectException(ServiceCreationFailed::class);

        $container->get('a');
    }

    public function testAliasReplacesService(): void
    {
        $service = new stdClass();
        $container = new Container();
        $container->bind('service', new stdClass());
        $container->bind('other', $service);

        $container->alias('service', 'other');

        self::assertSame($service, $container->get('service'));
    }
}
