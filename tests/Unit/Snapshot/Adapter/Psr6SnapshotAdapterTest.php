<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6SnapshotAdapter::class)]
final class Psr6SnapshotAdapterTest extends TestCase
{
    public function testSaveSnapshot(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->atLeastOnce())->method('set')->with(['foo' => 'bar'])->willReturn($item);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('key')->willReturn($item);
        $cache->expects($this->atLeastOnce())->method('save')->with($item);

        $store = new Psr6SnapshotAdapter($cache);

        $store->save('key', ['foo' => 'bar']);
    }

    public function testLoadSnapshot(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['foo' => 'bar']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('key')->willReturn($item);

        $store = new Psr6SnapshotAdapter($cache);

        self::assertEquals(['foo' => 'bar'], $store->load('key'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('key')->willReturn($item);

        $store = new Psr6SnapshotAdapter($cache);
        $store->load('key');
    }
}
