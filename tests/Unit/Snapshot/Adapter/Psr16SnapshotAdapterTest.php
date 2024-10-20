<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\SimpleCache\CacheInterface;

/** @covers \Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter */
final class Psr16SnapshotAdapterTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->set('key', ['foo' => 'bar'])->shouldBeCalled();

        $store = new Psr16SnapshotAdapter($cache->reveal());

        $store->save('key', ['foo' => 'bar']);
    }

    public function testLoadSnapshot(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get('key')->willReturn(['foo' => 'bar']);

        $store = new Psr16SnapshotAdapter($cache->reveal());

        self::assertEquals(['foo' => 'bar'], $store->load('key'));
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
