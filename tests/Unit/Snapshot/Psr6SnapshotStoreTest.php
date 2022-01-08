<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use function sprintf;

/** @covers \Patchlevel\EventSourcing\Snapshot\Psr6SnapshotStore */
class Psr6SnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $item = $this->prophesize(CacheItemInterface::class);
        $item->set([
            'playhead' => 1,
            'payload' => ['foo' => 'bar'],
        ])->shouldBeCalled()->willReturn($item);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', ProfileWithSnapshot::class))->willReturn($item);
        $cache->save($item)->shouldBeCalled();

        $store = new Psr6SnapshotStore($cache->reveal());

        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            ['foo' => 'bar']
        );

        $store->save($snapshot);
    }

    public function testLoadSnapshot(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            ['foo' => 'bar']
        );

        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(true);
        $item->get()->willReturn([
            'playhead' => 1,
            'payload' => ['foo' => 'bar'],
        ]);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', ProfileWithSnapshot::class))->willReturn($item);

        $store = new Psr6SnapshotStore($cache->reveal());

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $item = $this->prophesize(CacheItemInterface::class);
        $item->isHit()->willReturn(false);

        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache->getItem(sprintf('%s-1', ProfileWithSnapshot::class))->willReturn($item);

        $store = new Psr6SnapshotStore($cache->reveal());
        $store->load(ProfileWithSnapshot::class, '1');
    }
}
