<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use function sprintf;

class Psr6SnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $item = $this->prophesize(CacheItemInterface::class);
        $item->set([
            'playhead' => 0,
            'payload' => ['foo' => 'bar'],
        ])->shouldBeCalled();

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', Profile::class))->willReturn($item);
        $cache->save($item)->shouldBeCalled();

        $store = new Psr6SnapshotStore($cache->reveal());

        $snapshot = new Snapshot(
            Profile::class,
            '1',
            0,
            ['foo' => 'bar']
        );

        $store->save($snapshot);
    }

    public function testLoadSnapshot(): void
    {
        $snapshot = new Snapshot(
            Profile::class,
            '1',
            0,
            ['foo' => 'bar']
        );

        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(true);
        $item->get()->willReturn([
            'playhead' => 0,
            'payload' => ['foo' => 'bar'],
        ]);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', Profile::class))->willReturn($item);

        $store = new Psr6SnapshotStore($cache->reveal());

        self::assertEquals($snapshot, $store->load(Profile::class, '1'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(false);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', Profile::class))->willReturn($item);

        $store = new Psr6SnapshotStore($cache->reveal());
        $store->load(Profile::class, '1');
    }
}
