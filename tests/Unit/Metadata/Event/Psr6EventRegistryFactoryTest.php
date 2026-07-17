<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventRegistryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6EventRegistryFactory::class)]
final class Psr6EventRegistryFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new EventRegistry([]);

        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item
            ->expects($this->once())
            ->method('get')
            ->willReturn($value);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('event_registry')
            ->willReturn($item);
        $cache
            ->expects($this->never())
            ->method('save');

        $innerFactory = $this->createMock(EventRegistryFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('create');

        $factory = new Psr6EventRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }

    public function testCacheMiss(): void
    {
        $value = new EventRegistry([]);

        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $item
            ->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('event_registry')
            ->willReturn($item);
        $cache
            ->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $innerFactory = $this->createMock(EventRegistryFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('create')
            ->with(['/foo'])
            ->willReturn($value);

        $factory = new Psr6EventRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }
}
