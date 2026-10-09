<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootRegistryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6AggregateRootRegistryFactory::class)]
final class Psr6AggregateRootRegistryFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new AggregateRootRegistry([]);

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
            ->with('aggregate_root_registry')
            ->willReturn($item);
        $cache
            ->expects($this->never())
            ->method('save');

        $innerFactory = $this->createMock(AggregateRootRegistryFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('create');

        $factory = new Psr6AggregateRootRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }

    public function testCacheMiss(): void
    {
        $value = new AggregateRootRegistry([]);

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
            ->with('aggregate_root_registry')
            ->willReturn($item);
        $cache
            ->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $innerFactory = $this->createMock(AggregateRootRegistryFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('create')
            ->with(['/foo'])
            ->willReturn($value);

        $factory = new Psr6AggregateRootRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }
}
