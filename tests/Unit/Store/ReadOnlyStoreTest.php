<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StoreIsReadOnly;
use Patchlevel\EventSourcing\Store\StreamStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReadOnlyStore::class)]
final class ReadOnlyStoreTest extends TestCase
{
    public function testUnsupportedStore(): void
    {
        $parentStore = $this->createMock(StreamStore::class);

        $this->expectException(InvalidArgumentException::class);
        new ReadOnlyStore($parentStore);
    }

    public function testLoad(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(Store::class);
        $parentStore->expects($this->atLeastOnce())->method('load')->with($criteria, 8, 42, true);

        $store = new ReadOnlyStore($parentStore);
        $store->load($criteria, 8, 42, true);
    }

    public function testCount(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->createMock(Store::class);
        $parentStore->expects($this->atLeastOnce())->method('count')->with($criteria);

        $store = new ReadOnlyStore($parentStore);
        $store->count($criteria);
    }

    public function testSave(): void
    {
        $message = new Message(new class () {
        });

        $parentStore = $this->createMock(Store::class);
        $parentStore->expects($this->never())->method('save')->with($message);

        $store = new ReadOnlyStore($parentStore);
        $this->expectException(StoreIsReadOnly::class);
        $store->save($message);
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $parentStore = $this->createMock(Store::class);
        $parentStore->expects($this->atLeastOnce())->method('transactional')->with($callback);

        $store = new ReadOnlyStore($parentStore);
        $store->transactional($callback);
    }
}
