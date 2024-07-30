<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\StreamHeader;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

use function iterator_to_array;

/** @covers \Patchlevel\EventSourcing\Store\InMemoryStore */
final class InMemoryStoreTest extends TestCase
{
    use ProphecyTrait;

    public function testLoadEmpty(): void
    {
        $store = new InMemoryStore();
        $stream = $store->load();

        self::assertCount(0, $stream);
    }

    public function testLoadMessages(): void
    {
        $expected = [
            new Message(new ProfileVisited(ProfileId::fromString('1'))),
            new Message(new ProfileVisited(ProfileId::fromString('2'))),
        ];

        $store = new InMemoryStore($expected);

        $stream = $store->load();

        $messages = iterator_to_array($stream);

        self::assertSame($expected, $messages);
    }

    public function testLoadByAggregateId(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new AggregateHeader('profile', '2', 1, new DateTimeImmutable()));
        $message3 = new Message(new ProfileVisited(ProfileId::fromString('3')));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new AggregateIdCriterion('2')));

        $messages = iterator_to_array($stream);

        self::assertSame([$message2], $messages);
    }

    public function testLoadByAggregateName(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new AggregateHeader('foo', '1', 1, new DateTimeImmutable()));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new AggregateHeader('bar', '2', 1, new DateTimeImmutable()));
        $message3 = new Message(new ProfileVisited(ProfileId::fromString('3')));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new AggregateNameCriterion('bar')));

        $messages = iterator_to_array($stream);

        self::assertSame([$message2], $messages);
    }

    public function testLoadByStreamName(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamHeader('foo'));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamHeader('bar'));
        $message3 = new Message(new ProfileVisited(ProfileId::fromString('3')));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('bar')));

        $messages = iterator_to_array($stream);

        self::assertSame([$message2], $messages);
    }

    public function testLoadByStreamNameWithLike(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamHeader('foo-3'));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamHeader('bar-1'));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('bar-2'));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('bar-*')));

        $messages = iterator_to_array($stream);

        self::assertSame([$message2, $message3], $messages);
    }

    public function testLoadFromPlayhead(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new AggregateHeader('foo', '1', 1, new DateTimeImmutable()));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new AggregateHeader('foo', '1', 2, new DateTimeImmutable()));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('foo-1', 3, new DateTimeImmutable()));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $stream = $store->load(new Criteria(new FromPlayheadCriterion(2)));

        $messages = iterator_to_array($stream);

        self::assertSame([$message2, $message3], $messages);
    }

    public function testLoadFromIndex(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new AggregateHeader('foo', '1', 1, new DateTimeImmutable()));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new AggregateHeader('foo', '1', 2, new DateTimeImmutable()));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('foo-1', 3, new DateTimeImmutable()));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $stream = $store->load(new Criteria(new FromIndexCriterion(2)));

        $messages = iterator_to_array($stream);

        self::assertSame([$message3, $message4], $messages);
    }

    public function testLoadByStreamNameWithLikeAll(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamHeader('foo-3'));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamHeader('bar-1'));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('bar-2'));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('*')));

        $messages = iterator_to_array($stream);

        self::assertSame([$message1, $message2, $message3], $messages);
    }

    public function testLoadArchived(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new ArchivedHeader());
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(new Criteria(new ArchivedCriterion(true)));

        $messages = iterator_to_array($stream);

        self::assertSame([$message1], $messages);
    }

    public function testLoadUnsupportedCriterion(): void
    {
        $store = new InMemoryStore([
            new Message(new ProfileVisited(ProfileId::fromString('1'))),
            new Message(new ProfileVisited(ProfileId::fromString('2'))),
        ]);

        $this->expectException(UnsupportedCriterion::class);

        $store->load(new Criteria(new stdClass()));
    }

    public function testLoadLimit(): void
    {
        $message1 = new Message(new ProfileVisited(ProfileId::fromString('1')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('2')));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, 1);

        $messages = iterator_to_array($stream);

        self::assertSame([$message1], $messages);
    }

    public function testLoadOffset(): void
    {
        $message1 = new Message(new ProfileVisited(ProfileId::fromString('1')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('2')));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, null, 1);

        $messages = iterator_to_array($stream);

        self::assertSame([$message2], $messages);
    }

    public function testLoadBackwards(): void
    {
        $message1 = new Message(new ProfileVisited(ProfileId::fromString('1')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('2')));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, null, null, true);

        $messages = iterator_to_array($stream);

        self::assertSame([$message2, $message1], $messages);
    }

    public function testCount(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new ArchivedHeader());
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))));

        $store = new InMemoryStore([$message1, $message2]);

        self::assertSame(1, $store->count(new Criteria(new ArchivedCriterion(true))));
    }

    public function testSaveEmpty(): void
    {
        $expected = [
            new Message(new ProfileVisited(ProfileId::fromString('1'))),
            new Message(new ProfileVisited(ProfileId::fromString('2'))),
        ];

        $store = new InMemoryStore([]);

        $store->save(...$expected);

        $stream = $store->load();

        $messages = iterator_to_array($stream);

        self::assertSame($expected, $messages);
    }

    public function testSaveWithExistingMessages(): void
    {
        $startMessages = [
            new Message(new ProfileVisited(ProfileId::fromString('1'))),
            new Message(new ProfileVisited(ProfileId::fromString('2'))),
        ];

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('3')));

        $store = new InMemoryStore($startMessages);

        $store->save($message1);

        $stream = $store->load();

        $messages = iterator_to_array($stream);

        self::assertSame([...$startMessages, $message1], $messages);
    }

    public function testStreams(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamHeader('foo'));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamHeader('bar'));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('bar'));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        self::assertSame(['foo', 'bar'], $store->streams());
    }

    public function testRemove(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamHeader('foo'));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamHeader('bar'));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamHeader('bar'));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $store->remove('bar');

        $stream = $store->load();

        $messages = iterator_to_array($stream);

        self::assertSame([$message1, $message4], $messages);
    }

    public function testTransactional(): void
    {
        $called = false;

        $store = new InMemoryStore();
        $store->transactional(
            static function () use (&$called): void {
                $called = true;
            },
        );

        self::assertTrue($called);
    }
}
