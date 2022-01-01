<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Psr16SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\SimpleCache\CacheInterface;

use function sprintf;

class Psr16SnapshotStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testSaveSnapshot(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->set(
            sprintf('%s-1', ProfileWithSnapshot::class),
            [
                'playhead' => 1,
                'payload' => ['foo' => 'bar'],
            ]
        )->shouldBeCalled();

        $store = new Psr16SnapshotStore($cache->reveal());

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

        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(sprintf('%s-1', ProfileWithSnapshot::class))->willReturn([
            'playhead' => 1,
            'payload' => ['foo' => 'bar'],
        ]);

        $store = new Psr16SnapshotStore($cache->reveal());

        self::assertEquals($snapshot, $store->load(ProfileWithSnapshot::class, '1'));
    }

    public function testSnapshotNotFound(): void
    {
        $this->expectException(SnapshotNotFound::class);

        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(sprintf('%s-1', ProfileWithSnapshot::class))->willReturn(null);

        $store = new Psr16SnapshotStore($cache->reveal());
        $store->load(ProfileWithSnapshot::class, '1');
    }
}
