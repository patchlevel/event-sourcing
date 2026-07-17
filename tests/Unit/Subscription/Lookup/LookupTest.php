<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Lookup;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use Patchlevel\EventSourcing\Subscription\Lookup\MessageNotFound;
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
        $expectedResult = new Stream([]);

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
        $expectedResult = new Stream([]);
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
        $expectedResult = new Stream([]);
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
        $expectedResult = new Stream([]);
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
        $expectedResult = new Stream([]);
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

    public function testCurrentStream(): void
    {
        $expectedResult = new Stream([]);
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

    public function testFetchFirst(): void
    {
        $message1 = new Message(new class () {
        });

        $message2 = new Message(new class () {
        });

        $expectedResult = new Stream([
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

        $expectedResult = new Stream([
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

    public function testResetStream(): void
    {
        $expectedResult = new Stream([]);
        $expectedCriteria = new Criteria(new ToIndexCriterion(1));

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

        $result = $lookup->stream('foo')->stream(null)->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testForward(): void
    {
        $expectedResult = new Stream([]);
        $expectedCriteria = new Criteria(new ToIndexCriterion(1));

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

        $result = $lookup->backwards()->forward()->fetchAll();

        self::assertSame($expectedResult, $result);
    }

    public function testFetchFirstNotFound(): void
    {
        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with(new Criteria(new ToIndexCriterion(1)), 1, null, false)
            ->willReturn(new Stream([]));

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $this->expectException(MessageNotFound::class);

        $lookup->fetchFirst();
    }

    public function testFetchLastNotFound(): void
    {
        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('load')->with(new Criteria(new ToIndexCriterion(1)), 1, null, true)
            ->willReturn(new Stream([]));

        $event = new class () {
        };

        $message = (new Message($event))
            ->withHeader(new IndexHeader(1));

        $lookup = new Lookup(
            $store,
            $message,
        );

        $this->expectException(MessageNotFound::class);

        $lookup->fetchLast();
    }

}
