<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr16EventRegistryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16EventRegistryFactory::class)]
final class Psr16EventRegistryFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new EventRegistry([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with('event_registry')
            ->willReturn($value);
        $cache
            ->expects($this->never())
            ->method('set');

        $innerFactory = $this->createMock(EventRegistryFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('create');

        $factory = new Psr16EventRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }

    public function testCacheMiss(): void
    {
        $value = new EventRegistry([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with('event_registry')
            ->willReturn(null);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with('event_registry', $value)
            ->willReturn(true);

        $innerFactory = $this->createMock(EventRegistryFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('create')
            ->with(['/foo'])
            ->willReturn($value);

        $factory = new Psr16EventRegistryFactory($innerFactory, $cache);

        self::assertSame($value, $factory->create(['/foo']));
    }
}
