<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Clock\ClockInterface;
use stdClass;

final class GapResolverStoreMessageLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(new FromIndexCriterion(0)))->willReturn(new ArrayStream([]));

        $loader = new GapResolverStoreMessageLoader($store->reveal());

        $stream = $loader->load(0, []);

        self::assertStream([], $stream);
    }

    public function testNoGap(): void
    {
        $store = $this->prophesize(Store::class);

        $store->load(new Criteria(new FromIndexCriterion(0)))->willReturn(new ArrayStream([
            1 => new Message(new stdClass()),
            2 => new Message(new stdClass()),
            3 => new Message(new stdClass()),
            4 => new Message(new stdClass()),
        ]))->shouldBeCalledOnce();

        $loader = new GapResolverStoreMessageLoader($store->reveal());

        $stream = $loader->load(0, []);

        self::assertStream([
            1 => new Message(new stdClass()),
            2 => new Message(new stdClass()),
            3 => new Message(new stdClass()),
            4 => new Message(new stdClass()),
        ], $stream);
    }

    public function testNoGapFromHigherIndex(): void
    {
        $store = $this->prophesize(Store::class);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            8 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ]))->shouldBeCalledOnce();

        $loader = new GapResolverStoreMessageLoader($store->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            8 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ], $stream);
    }

    public function testWithGapAndFill(): void
    {
        $store = $this->prophesize(Store::class);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ]))->shouldBeCalledOnce();

        $store->load(new Criteria(new FromIndexCriterion(7)))->willReturn(new ArrayStream([
            8 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ]))->shouldBeCalledOnce();

        $loader = new GapResolverStoreMessageLoader($store->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            8 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ], $stream);
    }

    public function testWithGapWithoutFill(): void
    {
        $store = $this->prophesize(Store::class);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ]))->shouldBeCalledOnce();

        $store->load(new Criteria(new FromIndexCriterion(7)))->willReturn(
            new ArrayStream([
                9 => new Message(new stdClass()),
            ]),
            new ArrayStream([
                9 => new Message(new stdClass()),
            ]),
            new ArrayStream([
                9 => new Message(new stdClass()),
            ]),
            new ArrayStream([
                9 => new Message(new stdClass()),
            ]),
        )->shouldBeCalledTimes(4);

        $loader = new GapResolverStoreMessageLoader($store->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ], $stream);
    }

    public function testGapAndInDetectionWindowForAggregateHeader(): void
    {
        $store = $this->prophesize(Store::class);
        $clock = $this->prophesize(ClockInterface::class);

        $clock->now()->willReturn(new DateTimeImmutable('2023-10-01 00:00:03'))->shouldBeCalledTimes(5);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
        ]))->shouldBeCalledOnce();

        $store->load(new Criteria(new FromIndexCriterion(7)))->willReturn(
            new ArrayStream([
                9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
        )->shouldBeCalledTimes(4);

        $loader = new GapResolverStoreMessageLoader($store->reveal(), $clock->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
        ], $stream);
    }

    public function testGapAndNotInDetectionWindowForAggregateHeader(): void
    {
        $store = $this->prophesize(Store::class);
        $clock = $this->prophesize(ClockInterface::class);

        $clock->now()->willReturn(new DateTimeImmutable('2023-12-01 00:00:00'))->shouldBeCalledTimes(1);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
        ]))->shouldBeCalledOnce();

        $loader = new GapResolverStoreMessageLoader($store->reveal(), $clock->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithAggregateHeader(new DateTimeImmutable('2023-10-01 00:00:02')),
        ], $stream);
    }

    public function testGapAndInDetectionWindowForRecordedOnHeader(): void
    {
        $store = $this->prophesize(Store::class);
        $clock = $this->prophesize(ClockInterface::class);

        $clock->now()->willReturn(new DateTimeImmutable('2023-10-01 00:00:03'))->shouldBeCalledTimes(5);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ]))->shouldBeCalledOnce();

        $store->load(new Criteria(new FromIndexCriterion(7)))->willReturn(
            new ArrayStream([
                9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
            new ArrayStream([
                9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
            ]),
        )->shouldBeCalledTimes(4);

        $loader = new GapResolverStoreMessageLoader($store->reveal(), $clock->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ], $stream);
    }

    public function testGapAndNotInDetectionWindowForRecordedOnHeader(): void
    {
        $store = $this->prophesize(Store::class);
        $clock = $this->prophesize(ClockInterface::class);

        $clock->now()->willReturn(new DateTimeImmutable('2023-12-01 00:00:00'))->shouldBeCalledTimes(1);

        $store->load(new Criteria(new FromIndexCriterion(5)))->willReturn(new ArrayStream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ]))->shouldBeCalledOnce();

        $loader = new GapResolverStoreMessageLoader($store->reveal(), $clock->reveal());

        $stream = $loader->load(5, []);

        self::assertStream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ], $stream);
    }

    private function createMessageWithAggregateHeader(DateTimeImmutable $dateTime): Message
    {
        return (new Message(new stdClass()))
            ->withHeader(new AggregateHeader('foo', '1', 1, $dateTime));
    }

    private function createMessageWithRecordedOn(DateTimeImmutable $dateTime): Message
    {
        return (new Message(new stdClass()))
            ->withHeader(new RecordedOnHeader($dateTime));
    }

    /** @param array<int, Message> $expected */
    private function assertStream(array $expected, Stream $actual): void
    {
        $result = [];

        foreach ($actual as $message) {
            $result[$actual->index()] = $message;
        }

        self::assertEquals($expected, $result);
    }
}
