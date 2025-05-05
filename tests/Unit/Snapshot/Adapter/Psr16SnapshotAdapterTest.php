<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16SnapshotAdapter::class)]
final class Psr16SnapshotAdapterTest extends TestCase
{
    public function testSaveSnapshot(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->atLeastOnce())->method('set')->with('key', ['foo' => 'bar']);

        $store = new Psr16SnapshotAdapter($cache);

        $store->save('key', ['foo' => 'bar']);
    }

    public function testLoadSnapshot(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->with('key')->willReturn(['foo' => 'bar']);

        $store = new Psr16SnapshotAdapter($cache);

        self::assertEquals(['foo' => 'bar'], $store->load('key'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->with('key')->willReturn(null);

        $store = new Psr16SnapshotAdapter($cache);
        $store->load('key');
    }
}
