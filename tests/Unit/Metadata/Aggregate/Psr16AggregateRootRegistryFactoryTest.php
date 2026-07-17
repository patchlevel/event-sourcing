<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr16AggregateRootRegistryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16AggregateRootRegistryFactory::class)]
final class Psr16AggregateRootRegistryFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new AggregateRootRegistry([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with('aggregate_root_registry')
            ->willReturn($value);
        $cache
            ->expects($this->never())
            ->method('set');

        $innerFactory = $this->createMock(AggregateRootRegistryFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('create');

        $factory = new Psr16AggregateRootRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }

    public function testCacheMiss(): void
    {
        $value = new AggregateRootRegistry([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with('aggregate_root_registry')
            ->willReturn(null);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with('aggregate_root_registry', $value)
            ->willReturn(true);

        $innerFactory = $this->createMock(AggregateRootRegistryFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('create')
            ->with(['/foo'])
            ->willReturn($value);

        $factory = new Psr16AggregateRootRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }
}
