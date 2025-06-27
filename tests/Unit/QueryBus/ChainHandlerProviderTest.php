<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\HandlerDescriptor;
use Patchlevel\EventSourcing\QueryBus\HandlerProvider;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\QueryBus\ChainHandlerProvider */
final class ChainHandlerProviderTest extends TestCase
{
    public function testEmpty(): void
    {
        $provider = new ChainHandlerProvider([]);

        $result = $provider->handlerForQuery(QueryProfile::class);

        self::assertCount(0, $result);
    }

    public function testFindHandler(): void
    {
        $handler1 = new HandlerDescriptor(static fn () => null);
        $handler2 = new HandlerDescriptor(static fn () => null);
        $handler3 = new HandlerDescriptor(static fn () => null);

        $provider1 = $this->createMock(HandlerProvider::class);
        $provider1->method('handlerForQuery')->with(QueryProfile::class)->willReturn([
            $handler1,
            $handler2,
        ]);

        $provider2 = $this->createMock(HandlerProvider::class);
        $provider2->method('handlerForQuery')->with(QueryProfile::class)->willReturn([$handler3]);

        $chainProvider = new ChainHandlerProvider([
            $provider1,
            $provider2,
        ]);

        $result = $chainProvider->handlerForQuery(QueryProfile::class);

        self::assertSame([$handler1, $handler2, $handler3], $result);
    }
}
