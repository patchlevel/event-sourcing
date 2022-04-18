<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\SimpleCache\CacheInterface;

/** @covers  \Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter */
class Psr16SnapshotAdapterTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->set('key', [1, ['foo' => 'bar']])->shouldBeCalled();

        $store = new Psr16SnapshotAdapter($cache->reveal());

        $store->save('key', 1, ['foo' => 'bar']);
    }

    public function testLoadSnapshot(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get('key')->willReturn([1, ['foo' => 'bar']]);

        $store = new Psr16SnapshotAdapter($cache->reveal());

        self::assertEquals([1, ['foo' => 'bar']], $store->load('key'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $cache = $this->prophesize(CacheInterface::class);
        $cache->get('key')->willReturn(null);

        $store = new Psr16SnapshotAdapter($cache->reveal());
        $store->load('key');
    }
}
