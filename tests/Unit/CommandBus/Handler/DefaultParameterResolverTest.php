<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use Patchlevel\EventSourcing\Attribute\Inject;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Patchlevel\EventSourcing\CommandBus\ServiceNotFound;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use stdClass;

/** @covers \Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver */
final class DefaultParameterResolverTest extends TestCase
{
    use ProphecyTrait;

    public function testNoParameters(): void
    {
        $class = new class () {
            public function handle(): void
            {
            }
        };

        $resolver = new DefaultParameterResolver();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                new stdClass(),
            ),
        ];

        self::assertSame([], $result);
    }

    public function testOnlyCommand(): void
    {
        $class = new class () {
            public function handle(stdClass $command): void
            {
            }
        };

        $resolver = new DefaultParameterResolver();

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command], $result);
    }

    public function testMissingContainer(): void
    {
        $this->expectException(ServiceNotResolvable::class);

        $class = new class () {
            // phpcs:disable
            public function handle(stdClass $command, $foo): void
            {
            }
            // phpcs:enable
        };

        $resolver = new DefaultParameterResolver();

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command], $result);
    }

    public function testNoType(): void
    {
        $this->expectException(ServiceNotResolvable::class);

        $class = new class () {
            // phpcs:disable
            public function handle(stdClass $command, $foo): void
            {
            }
            // phpcs:enable
        };

        $container = $this->prophesize(ContainerInterface::class);

        $resolver = new DefaultParameterResolver($container->reveal());

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command], $result);
    }

    public function testNoClass(): void
    {
        $this->expectException(ServiceNotResolvable::class);

        $class = new class () {
            public function handle(stdClass $command, string $foo): void
            {
            }
        };

        $container = $this->prophesize(ContainerInterface::class);

        $resolver = new DefaultParameterResolver($container->reveal());

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command], $result);
    }

    public function testMissingService(): void
    {
        $this->expectException(ServiceNotResolvable::class);

        $class = new class () {
            public function handle(stdClass $command, stdClass $foo): void
            {
            }
        };

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(stdClass::class)->willThrow(new ServiceNotFound(stdClass::class))->shouldBeCalledOnce();

        $resolver = new DefaultParameterResolver($container->reveal());

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command], $result);
    }

    public function testFindService(): void
    {
        $class = new class () {
            public function handle(stdClass $command, stdClass $foo): void
            {
            }
        };

        $service = new stdClass();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(stdClass::class)->willReturn($service)->shouldBeCalledOnce();

        $resolver = new DefaultParameterResolver($container->reveal());

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command, $service], $result);
    }

    public function testInject(): void
    {
        $class = new class () {
            public function handle(
                stdClass $command,
                #[Inject('foo')]
                stdClass $foo,
            ): void {
            }
        };

        $service = new stdClass();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('foo')->willReturn($service)->shouldBeCalledOnce();

        $resolver = new DefaultParameterResolver($container->reveal());

        $command = new stdClass();

        $result = [
            ...$resolver->resolve(
                new ReflectionMethod($class, 'handle'),
                $command,
            ),
        ];

        self::assertSame([$command, $service], $result);
    }
}
