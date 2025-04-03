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
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(ReadOnlyStore::class)]
final class ReadOnlyStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testUnsupportedStore(): void
    {
        $parentStore = $this->prophesize(StreamStore::class);

        $this->expectException(InvalidArgumentException::class);
        new ReadOnlyStore($parentStore->reveal());
    }

    public function testLoad(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(Store::class);
        $parentStore->load($criteria, 8, 42, true)->shouldBeCalled();

        $store = new ReadOnlyStore($parentStore->reveal());
        $store->load($criteria, 8, 42, true);
    }

    public function testCount(): void
    {
        $criteria = new Criteria();

        $parentStore = $this->prophesize(Store::class);
        $parentStore->count($criteria)->shouldBeCalled();

        $store = new ReadOnlyStore($parentStore->reveal());
        $store->count($criteria);
    }

    public function testSave(): void
    {
        $message = new Message(new class () {
        });

        $parentStore = $this->prophesize(Store::class);
        $parentStore->save($message)->shouldNotBeCalled();

        $store = new ReadOnlyStore($parentStore->reveal());
        $this->expectException(StoreIsReadOnly::class);
        $store->save($message);
    }

    public function testTransactional(): void
    {
        $callback = static function (): void {
        };

        $parentStore = $this->prophesize(Store::class);
        $parentStore->transactional($callback)->shouldBeCalled();

        $store = new ReadOnlyStore($parentStore->reveal());
        $store->transactional($callback);
    }
}
