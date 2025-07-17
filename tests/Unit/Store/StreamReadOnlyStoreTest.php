<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\StoreIsReadOnly;
use Patchlevel\EventSourcing\Store\StreamReadOnlyStore;
use Patchlevel\EventSourcing\Store\StreamStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamReadOnlyStore::class)]
final class StreamReadOnlyStoreTest extends TestCase
{
    public function testLoad(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->atLeastOnce())->method('load')->with($criteria, 8, 42, true);

        $store = new StreamReadOnlyStore($parentStore);
        $store->load($criteria, 8, 42, true);
    }

    public function testCount(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->atLeastOnce())->method('count')->with($criteria);

        $store = new StreamReadOnlyStore($parentStore);
        $store->count($criteria);
    }

    public function testSave(): void
    {
        $message = new Message(new class () {
        });

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->never())->method('save')->with($message);

        $store = new StreamReadOnlyStore($parentStore);
        $this->expectException(StoreIsReadOnly::class);
        $store->save($message);
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->atLeastOnce())->method('transactional')->with($callback);

        $store = new StreamReadOnlyStore($parentStore);
        $store->transactional($callback);
    }

    public function testStreams(): void
    {
        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->atLeastOnce())->method('streams')->willReturn(['foo', 'bar']);

        $store = new StreamReadOnlyStore($parentStore);

        self::assertEquals(['foo', 'bar'], $store->streams());
    }

    public function testRemove(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->never())->method('remove')->with($criteria);

        $store = new StreamReadOnlyStore($parentStore);
        $this->expectException(StoreIsReadOnly::class);
        $store->remove($criteria);
    }

    public function testArchive(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(StreamStore::class);
        $parentStore->expects($this->never())->method('archive')->with($criteria);

        $store = new StreamReadOnlyStore($parentStore);
        $this->expectException(StoreIsReadOnly::class);
        $store->archive($criteria);
    }
}
