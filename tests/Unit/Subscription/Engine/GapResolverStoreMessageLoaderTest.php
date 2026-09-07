<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;

final class GapResolverStoreMessageLoaderTest extends TestCase
{
    public function testEmpty(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('load')->with(new Criteria(new FromIndexCriterion(0)))->willReturn(new Stream([]));

        $loader = new GapResolverStoreMessageLoader($store);

        $stream = $loader->load(0, []);

        $this->assertEqualsStream([], $stream);
    }

    public function testNoGap(): void
    {
        $store = $this->createMock(Store::class);

        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(0)))
            ->willReturn(new Stream([
                1 => new Message(new stdClass()),
                2 => new Message(new stdClass()),
                3 => new Message(new stdClass()),
                4 => new Message(new stdClass()),
            ]));

        $loader = new GapResolverStoreMessageLoader($store);

        $stream = $loader->load(0, []);

        $this->assertEqualsStream([
            1 => new Message(new stdClass()),
            2 => new Message(new stdClass()),
            3 => new Message(new stdClass()),
            4 => new Message(new stdClass()),
        ], $stream);
    }

    public function testNoGapFromHigherIndex(): void
    {
        $store = $this->createMock(Store::class);

        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(new FromIndexCriterion(5)))
            ->willReturn(new Stream([
                6 => new Message(new stdClass()),
                7 => new Message(new stdClass()),
                8 => new Message(new stdClass()),
                9 => new Message(new stdClass()),
            ]));

        $loader = new GapResolverStoreMessageLoader($store);

        $stream = $loader->load(5, []);

        $this->assertEqualsStream(
            [
                6 => new Message(new stdClass()),
                7 => new Message(new stdClass()),
                8 => new Message(new stdClass()),
                9 => new Message(new stdClass()),
            ],
            $stream,
        );
    }

    public function testWithGapAndFill(): void
    {
        $store = $this->createMock(Store::class);

        $store
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(
                static fn (Criteria $criteria) => match ($criteria->get(FromIndexCriterion::class)->fromIndex) {
                    5 => new Stream([
                        6 => new Message(new stdClass()),
                        7 => new Message(new stdClass()),
                        9 => new Message(new stdClass()),
                    ]),
                    7 => new Stream([
                        8 => new Message(new stdClass()),
                        9 => new Message(new stdClass()),
                    ]),
                    default => new RuntimeException('Unmatched case!')
                },
            );

        $loader = new GapResolverStoreMessageLoader($store);

        $stream = $loader->load(5, []);

        $this->assertEqualsStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            8 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ], $stream);
    }

    public function testWithGapWithoutFill(): void
    {
        $store = $this->createMock(Store::class);

        $store
            ->expects($this->exactly(5))
            ->method('load')
            ->willReturnCallback(static fn (Criteria $criteria) => match ($criteria->get(FromIndexCriterion::class)->fromIndex) {
                5 => new Stream([
                    6 => new Message(new stdClass()),
                    7 => new Message(new stdClass()),
                    9 => new Message(new stdClass()),
                ]),
                7 => new Stream([
                    9 => new Message(new stdClass()),
                ]),
                default => new RuntimeException('Unmatched case!')
            });

        $loader = new GapResolverStoreMessageLoader($store);

        $stream = $loader->load(5, []);

        $this->assertEqualsStream([
            6 => new Message(new stdClass()),
            7 => new Message(new stdClass()),
            9 => new Message(new stdClass()),
        ], $stream);
    }

    public function testGapAndInDetectionWindowForRecordedOnHeader(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->exactly(5))
            ->method('now')
            ->willReturn(new DateTimeImmutable('2023-10-01 00:00:03'));

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->exactly(5))
            ->method('load')
            ->willReturnCallback(fn (Criteria $criteria) => match ($criteria->get(FromIndexCriterion::class)->fromIndex) {
                5 => new Stream([
                    6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
                    7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
                    9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
                ]),
                7 => new Stream([
                    9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
                ]),
                default => new RuntimeException('Unmatched case!')
            });

        $loader = new GapResolverStoreMessageLoader($store, $clock);
        $stream = $loader->load(5, []);

        $this->assertEqualsStream(
            [
                6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
                7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
                9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
            ],
            $stream,
        );
    }

    public function testGapAndNotInDetectionWindowForRecordedOnHeader(): void
    {
        $store = $this->createMock(Store::class);
        $clock = $this->createMock(ClockInterface::class);

        $clock->expects($this->exactly(1))->method('now')->willReturn(new DateTimeImmutable('2023-12-01 00:00:00'));

        $store->expects($this->once())->method('load')->with(new Criteria(new FromIndexCriterion(5)))->willReturn(new Stream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ]));

        $loader = new GapResolverStoreMessageLoader($store, $clock);

        $stream = $loader->load(5, []);

        $this->assertEqualsStream([
            6 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:00')),
            7 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:01')),
            9 => $this->createMessageWithRecordedOn(new DateTimeImmutable('2023-10-01 00:00:02')),
        ], $stream);
    }

    private function createMessageWithRecordedOn(DateTimeImmutable $dateTime): Message
    {
        return (new Message(new stdClass()))
            ->withHeader(new RecordedOnHeader($dateTime));
    }

    /** @param array<int, Message> $expected */
    private function assertEqualsStream(array $expected, Stream $actual): void
    {
        $result = [];

        foreach ($actual as $message) {
            $index = $actual->index();
            self::assertNotNull($index);

            $result[$index] = $message;
        }

        self::assertEquals($expected, $result);
    }
}
