<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Lookup;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStore;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use Patchlevel\EventSourcing\Subscription\Lookup\MissingContext;
use Patchlevel\EventSourcing\Subscription\Lookup\MissingIndex;
use Patchlevel\EventSourcing\Subscription\Lookup\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Subscription\Lookup\Lookup */
final class LookupTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingIndexHeader(): void
    {
        $store = $this->prophesize(Store::class);
        $eventRegistry = new EventRegistry([]);

        $event = new class () {
        };

        $message = new Message($event);

        $lookup = new Lookup(
            $store->reveal(),
            $eventRegistry,
            $message,
        );

        $this->expectException(MissingIndex::class);

        $lookup->queryBuilder();
    }

    public function testMissingContext(): void
    {
        $store = $this->prophesize(Store::class);
        $eventRegistry = new EventRegistry([]);

        $event = new class () {
        };

        $message = (new Message($event))->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store->reveal(),
            $eventRegistry,
            $message,
        );

        $this->expectException(MissingContext::class);

        $lookup->queryBuilder();
    }

    public function testAggregateStore(): void
    {
        $store = $this->prophesize(Store::class);
        $eventRegistry = new EventRegistry([]);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1))
            ->withHeader(
                new AggregateHeader(
                    'foo',
                    'bar',
                    1,
                    new DateTimeImmutable(),
                ),
            );

        $lookup = new Lookup(
            $store->reveal(),
            $eventRegistry,
            $message,
        );

        $queryBuilder = $lookup->queryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testStreamStore(): void
    {
        $store = $this->prophesize(Store::class);
        $store->willImplement(StreamStore::class);

        $eventRegistry = new EventRegistry([]);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1))
            ->withHeader(new StreamNameHeader('foo'));

        $lookup = new Lookup(
            $store->reveal(),
            $eventRegistry,
            $message,
        );

        $queryBuilder = $lookup->queryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }
}
