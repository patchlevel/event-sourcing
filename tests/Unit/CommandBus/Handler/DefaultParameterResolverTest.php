<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use Patchlevel\EventSourcing\Attribute\Inject;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use Patchlevel\EventSourcing\CommandBus\ServiceNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use stdClass;

#[CoversClass(DefaultParameterResolver::class)]
final class DefaultParameterResolverTest extends TestCase
{
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
            public function handle(stdClass $command, mixed $foo): void
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
            public function handle(stdClass $command, mixed $foo): void
            {
            }
            // phpcs:enable
        };

        $container = $this->createMock(ContainerInterface::class);

        $resolver = new DefaultParameterResolver($container);

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

        $container = $this->createMock(ContainerInterface::class);

        $resolver = new DefaultParameterResolver($container);

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

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(stdClass::class)
            ->willThrowException(new ServiceNotFound(stdClass::class));

        $resolver = new DefaultParameterResolver($container);

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

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(stdClass::class)
            ->willReturn($service);

        $resolver = new DefaultParameterResolver($container);

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

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($service);

        $resolver = new DefaultParameterResolver($container);

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
