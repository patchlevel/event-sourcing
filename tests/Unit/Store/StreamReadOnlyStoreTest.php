<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\StoreIsReadOnly;
use Patchlevel\EventSourcing\Store\StreamReadOnlyStore;
use Patchlevel\EventSourcing\Store\StreamStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(ReadOnlyStore::class)]
final class StreamReadOnlyStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testLoad(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->load($criteria, 8, 42, true)->shouldBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $store->load($criteria, 8, 42, true);
    }

    public function testCount(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->count($criteria)->shouldBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $store->count($criteria);
    }

    public function testSave(): void
    {
        $message = new Message(new class () {
        });

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->save($message)->shouldNotBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $this->expectException(StoreIsReadOnly::class);
        $store->save($message);
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->transactional($callback)->shouldBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $store->transactional($callback);
    }

    public function testStreams(): void
    {
        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->streams()->willReturn(['foo', 'bar'])->shouldBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());

        self::assertEquals(['foo', 'bar'], $store->streams());
    }

    public function testRemove(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->remove($criteria)->shouldNotBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $this->expectException(StoreIsReadOnly::class);
        $store->remove($criteria);
    }

    public function testArchive(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(StreamStore::class);
        $parentStore->archive($criteria)->shouldNotBeCalled();

        $store = new StreamReadOnlyStore($parentStore->reveal());
        $this->expectException(StoreIsReadOnly::class);
        $store->archive($criteria);
    }
}
