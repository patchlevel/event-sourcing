<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/** @covers \Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter */
class Psr6SnapshotAdapterTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $item = $this->prophesize(CacheItemInterface::class);
        $item->set(['foo' => 'bar'])->shouldBeCalled()->willReturn($item);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem('key')->willReturn($item);
        $cache->save($item)->shouldBeCalled();

        $store = new Psr6SnapshotAdapter($cache->reveal());

        $store->save('key', ['foo' => 'bar']);
    }

    public function testLoadSnapshot(): void
    {
        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(true);
        $item->get()->willReturn(['foo' => 'bar']);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem('key')->willReturn($item);

        $store = new Psr6SnapshotAdapter($cache->reveal());

        self::assertEquals(['foo' => 'bar'], $store->load('key'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(false);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem('key')->willReturn($item);

        $store = new Psr6SnapshotAdapter($cache->reveal());
        $store->load('key');
    }
}
