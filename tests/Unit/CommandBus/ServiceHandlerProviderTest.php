<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BaseCommand;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SomeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandlerProvider::class)]
final class ServiceHandlerProviderTest extends TestCase
{
    public function testEmpty(): void
    {
        $provider = new ServiceHandlerProvider([]);
        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertCount(0, $result);
    }

    public function testFindHandler(): void
    {
        $class = new class () {
            #[Handle(CreateProfile::class)]
            public function handle(): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertCount(1, $result);
        self::assertEquals($class->handle(...), $result[0]->callable());
    }

    public function testFindStaticHandler(): void
    {
        $class = new class () {
            #[Handle(CreateProfile::class)]
            public static function handle(): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertCount(1, $result);
        self::assertEquals($class::handle(...), $result[0]->callable());
    }

    public function testParameterResolver(): void
    {
        $class = new class () {
            /** @var list<mixed> */
            public array $arguments = [];

            #[Handle]
            public function handle(CreateProfile $command, string $extra): void
            {
                $this->arguments = [$command, $extra];
            }
        };

        $command = new CreateProfile(ProfileId::fromString('1'), 'info');

        $parameterResolver = $this->createMock(ParameterResolver::class);
        $parameterResolver->method('resolve')->willReturn([$command, 'foo']);

        $provider = new ServiceHandlerProvider([$class], $parameterResolver);

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertCount(1, $result);
        self::assertSame($class::class . '::handle', $result[0]->name());

        ($result[0]->callable())($command);

        self::assertSame([$command, 'foo'], $class->arguments);
    }

    public function testParameterResolverIsSkippedForHandlersWithOnlyTheCommand(): void
    {
        $class = new class () {
            #[Handle(CreateProfile::class)]
            public function handle(): void
            {
            }
        };

        $parameterResolver = $this->createMock(ParameterResolver::class);
        $parameterResolver->expects($this->never())->method('resolve');

        $provider = new ServiceHandlerProvider([$class], $parameterResolver);

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertEquals($class->handle(...), $result[0]->callable());
    }

    public function testFindHandlerByInterface(): void
    {
        $command = new class () implements SomeCommand {
        };

        $class = new class () {
            #[Handle]
            public function handle(SomeCommand $command): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = [...$provider->handlerForCommand($command::class)];

        self::assertCount(1, $result);
        self::assertEquals($class->handle(...), $result[0]->callable());
    }

    public function testFindHandlerByParentClass(): void
    {
        $command = new class () extends BaseCommand {
        };

        $class = new class () {
            #[Handle]
            public function handle(BaseCommand $command): void
            {
            }
        };

        $provider = new ServiceHandlerProvider([$class]);

        $result = [...$provider->handlerForCommand($command::class)];

        self::assertCount(1, $result);
        self::assertEquals($class->handle(...), $result[0]->callable());
    }
}
