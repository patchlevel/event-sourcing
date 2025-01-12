<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider */
final class ServiceHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $provider = new ServiceHandlerProvider([]);
        $result = $provider->handlerForCommand(CreateProfile::class);

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

        $result = $provider->handlerForCommand(CreateProfile::class);

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

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(1, $result);
        self::assertEquals($class::handle(...), $result[0]->callable());
    }
}
