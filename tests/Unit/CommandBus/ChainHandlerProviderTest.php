<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\ChainHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\HandlerDescriptor;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(ChainHandlerProvider::class)]
final class ChainHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $provider = new ChainHandlerProvider([]);

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(0, $result);
    }

    public function testFindHandler(): void
    {
        $handler1 = new HandlerDescriptor(static fn () => null);
        $handler2 = new HandlerDescriptor(static fn () => null);
        $handler3 = new HandlerDescriptor(static fn () => null);

        $provider1 = $this->prophesize(HandlerProvider::class);
        $provider1->handlerForCommand(CreateProfile::class)->willReturn([
            $handler1,
            $handler2,
        ]);

        $provider2 = $this->prophesize(HandlerProvider::class);
        $provider2->handlerForCommand(CreateProfile::class)->willReturn([$handler3]);

        $chainProvider = new ChainHandlerProvider([
            $provider1->reveal(),
            $provider2->reveal(),
        ]);

        $result = $chainProvider->handlerForCommand(CreateProfile::class);

        self::assertSame([$handler1, $handler2, $handler3], $result);
    }
}
