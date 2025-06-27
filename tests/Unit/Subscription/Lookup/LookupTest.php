<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Lookup;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lookup::class)]
final class LookupTest extends TestCase
{
    public function testMissingIndexHeader(): void
    {
        $store = $this->createMock(Store::class);

        $event = new class () {
        };

        $message = new Message($event);

        $this->expectException(HeaderNotFound::class);

        new Lookup(
            $store,
            $message,
        );
    }

    public function testEmpty(): void
    {
        $expectedResult = new ArrayStream([]);

        $store = $this->createMock(Store::class);
        $expectedCriteria = new Criteria(new ToIndexCriterion(1));

        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testEvents(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new EventsCriterion(['foo']),
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->events('foo')->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testEventClasses(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new EventsCriterion(['foo', 'profile_created']),
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
            new EventRegistry(['profile_created' => ProfileCreated::class]),
        );

        $result = $lookup->events('foo', ProfileCreated::class)->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testBackwards(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, true)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->backwards()->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testStream(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new StreamCriterion('foo'),
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->stream('foo')->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testAggregateName(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new AggregateNameCriterion('foo'),
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->aggregateName('foo')->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testAggregateId(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new AggregateIdCriterion('foo'),
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->aggregateId('foo')->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testCurrentStream(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new ToIndexCriterion(1),
            new StreamCriterion('foo'),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->currentStream()->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testCurrentAggregate(): void
    {
        $expectedResult = new ArrayStream([]);
        $expectedCriteria = new Criteria(
            new ToIndexCriterion(1),
            new AggregateNameCriterion('foo'),
            new AggregateIdCriterion('bar'),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, null, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new AggregateHeader(
                'foo',
                'bar',
                1,
                new DateTimeImmutable(),
            ))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->currentAggregate()->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testFetchFirst(): void
    {
        $message1 = new Message(new class () {
        });

        $message2 = new Message(new class () {
        });

        $expectedResult = new ArrayStream([
            $message1,
            $message2,
        ]);

        $expectedCriteria = new Criteria(
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, 1, null, false)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->fetchFirst();

        self::assertSame($message1, $result);
    }

    public function testFetchLast(): void
    {
        $message1 = new Message(new class () {
        });

        $message2 = new Message(new class () {
        });

        $expectedResult = new ArrayStream([
            $message2,
            $message1,
        ]);

        $expectedCriteria = new Criteria(
            new ToIndexCriterion(1),
        );

        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with($expectedCriteria, 1, null, true)
            ->willReturn($expectedResult);

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $result = $lookup->fetchLast();

        self::assertSame($message2, $result);
    }
}
