<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\MissingEventRegistry;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function iterator_to_array;

#[CoversClass(InMemoryStore::class)]
final class InMemoryStoreTest extends TestCase
{
    public function testLoadEmpty(): void
    {
        $store = new InMemoryStore();
        $stream = $store->load();

        self::assertCount(0, $stream);
    }

    public function testLoadMessages(): void
    {
        $expected = [
            (new Message(new ProfileVisited(ProfileId::fromString('1'))))
                ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(1)),
            (new Message(new ProfileVisited(ProfileId::fromString('2'))))
                ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(2)),
        ];

        $store = new InMemoryStore($expected);

        $stream = $store->load();

        self::assertSame($expected, $stream->toList());
    }

    public function testLoadByStreamName(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('bar')));

        self::assertSame([$message2], $stream->toList());
    }

    public function testLoadByStreamNameWithLike(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo-3'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar-1'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('bar-2'))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('bar-*')));

        self::assertSame([$message2, $message3], $stream->toList());
    }

    public function testLoadFromPlayhead(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new PlayheadHeader(2))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('foo-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $stream = $store->load(new Criteria(new FromPlayheadCriterion(2)));

        self::assertSame([$message2, $message3], $stream->toList());
    }

    public function testLoadFromIndex(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new PlayheadHeader(2))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('foo-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $stream = $store->load(new Criteria(new FromIndexCriterion(2)));

        $messages = $stream->toList();

        self::assertCount(2, $messages);
        self::assertSame(
            $message3->header(PlayheadHeader::class)->playhead,
            $messages[0]->header(PlayheadHeader::class)->playhead,
        );
        self::assertSame(
            3,
            $messages[0]->header(IndexHeader::class)->index,
        );
        self::assertFalse($message4->hasHeader(PlayheadHeader::class));
        self::assertSame(
            4,
            $messages[1]->header(IndexHeader::class)->index,
        );
    }

    public function testLoadToIndex(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('foo-1'))
            ->withHeader(new PlayheadHeader(2))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('foo-1'))
            ->withHeader(new PlayheadHeader(3))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $stream = $store->load(new Criteria(new ToIndexCriterion(3)));

        $messages = $stream->toList();

        self::assertCount(2, $messages);
        self::assertSame(
            $message1->header(PlayheadHeader::class)->playhead,
            $messages[0]->header(PlayheadHeader::class)->playhead,
        );
        self::assertSame(
            1,
            $messages[0]->header(IndexHeader::class)->index,
        );
        self::assertSame(
            $message2->header(PlayheadHeader::class)->playhead,
            $messages[1]->header(PlayheadHeader::class)->playhead,
        );
        self::assertSame(
            2,
            $messages[1]->header(IndexHeader::class)->index,
        );
    }

    public function testLoadByStreamNameWithLikeAll(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo-3'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar-1'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('bar-2'))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $stream = $store->load(new Criteria(new StreamCriterion('*')));

        self::assertSame([$message1, $message2, $message3], $stream->toList());
    }

    public function testLoadArchived(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new ArchivedHeader())
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(new Criteria(new ArchivedCriterion(true)));

        self::assertSame([$message1], $stream->toList());
    }

    public function testLoadByEventName(): void
    {
        $message1 = (new Message(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s@b.de'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore(
            [$message1, $message2, $message3],
            new EventRegistry([
                'profile_created' => ProfileCreated::class,
                'profile_visited' => ProfileVisited::class,
            ]),
        );

        $stream = $store->load(new Criteria(new EventsCriterion(['profile_created'])));

        self::assertSame([$message1], $stream->toList());

        $stream = $store->load(new Criteria(new EventsCriterion(['profile_visited'])));

        self::assertSame([$message2, $message3], $stream->toList());
        self::assertSame([], $store->load(new Criteria(new EventsCriterion(['profile_deleted'])))->toList());
    }

    public function testLoadByEventNameWithoutRegistry(): void
    {
        $message1 = (new Message(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('s@b.de'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore([$message1, $message2, $message3]);

        $this->expectException(MissingEventRegistry::class);
        $store->load(new Criteria(new EventsCriterion(['profile_created'])));
    }

    public function testLoadUnsupportedCriterion(): void
    {
        $store = new InMemoryStore([
            (new Message(new ProfileVisited(ProfileId::fromString('1'))))
                ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(1)),
            (new Message(new ProfileVisited(ProfileId::fromString('2'))))
                ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(2)),
        ]);

        $this->expectException(UnsupportedCriterion::class);

        $store->load(new Criteria(new stdClass()));
    }

    public function testLoadLimit(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, 1);

        self::assertSame([$message1], $stream->toList());
    }

    public function testLoadOffset(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, null, 1);

        self::assertSame([$message2], $stream->toList());
    }

    public function testLoadBackwards(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $stream = $store->load(null, null, null, true);

        self::assertSame([$message2, $message1], $stream->toList());
    }

    public function testCount(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new ArchivedHeader())
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        self::assertSame(1, $store->count(new Criteria(new ArchivedCriterion(true))));
    }

    public function testSaveEmpty(): void
    {
        $expected = [
            (new Message(new ProfileVisited(ProfileId::fromString('1'))))
                ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(1)),
            (new Message(new ProfileVisited(ProfileId::fromString('2'))))
                ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(2)),
        ];

        $store = new InMemoryStore([]);

        $store->save(...$expected);

        $stream = $store->load();

        self::assertSame($expected, $stream->toList());
    }

    public function testSaveWithExistingMessages(): void
    {
        $startMessages = [
            (new Message(new ProfileVisited(ProfileId::fromString('1'))))
                ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(1)),
            (new Message(new ProfileVisited(ProfileId::fromString('2'))))
                ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
                ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
                ->withHeader(new IndexHeader(2)),
        ];

        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));

        $store = new InMemoryStore($startMessages);

        $store->save($message1);

        $stream = $store->load();

        self::assertSame([...$startMessages, $message1], $stream->toList());
    }

    public function testSaveWithoutHeaders(): void
    {
        $store = new InMemoryStore([new Message(new ProfileVisited(ProfileId::fromString('3')))]);
        $store->save(new Message(new ProfileVisited(ProfileId::fromString('1'))));

        $stream = $store->load();
        $messages = $stream->toList();

        self::assertCount(2, $messages);

        foreach ($messages as $message) {
            self::assertTrue($message->hasHeader(EventIdHeader::class));
            self::assertTrue($message->hasHeader(RecordedOnHeader::class));
            self::assertTrue($message->hasHeader(IndexHeader::class));
        }
    }

    public function testStreams(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        self::assertSame(['foo', 'bar'], $store->streams());
    }

    public function testRemove(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $store->remove(new Criteria(new StreamCriterion('bar')));

        $stream = $store->load();

        self::assertSame([$message1, $message4], $stream->toList());
    }

    public function testArchive(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $store->archive(new Criteria(new StreamCriterion('bar')));

        $messages = $store->load()->toList();

        self::assertCount(2, $messages);
        self::assertFalse($messages[0]->hasHeader(ArchivedHeader::class));
        self::assertTrue($messages[1]->hasHeader(ArchivedHeader::class));

        $archivedMessages = $store->load(new Criteria(new ArchivedCriterion(true)))->toList();

        self::assertCount(1, $archivedMessages);
        self::assertSame('bar', $archivedMessages[0]->header(StreamNameHeader::class)->streamName);
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

    public function testTransactionalThrowAndResetting(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));

        $called = false;
        $catched = false;

        $store = new InMemoryStore([$message1]);

        try {
            $store->transactional(
                static function () use (&$called, $message2, $store): void {
                    $called = true;
                    $store->save($message2);

                    throw new RuntimeException('test');
                },
            );
        } catch (RuntimeException) {
            $catched = true;
        }

        self::assertTrue($catched);
        self::assertTrue($called);
        self::assertCount(1, iterator_to_array($store->load()));
    }

    public function testClear(): void
    {
        $message1 = (new Message(new ProfileVisited(ProfileId::fromString('1'))))
            ->withHeader(new StreamNameHeader('foo'))
            ->withHeader(new EventIdHeader('019aa600-56ef-7ca3-b92a-37c53851e2c2'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(1));
        $message2 = (new Message(new ProfileVisited(ProfileId::fromString('2'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa600-8834-752a-ae2e-d8650e84f403'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(2));
        $message3 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new StreamNameHeader('bar'))
            ->withHeader(new EventIdHeader('019aa604-94b8-7182-b1dc-f5d4aa9652ca'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(3));
        $message4 = (new Message(new ProfileVisited(ProfileId::fromString('3'))))
            ->withHeader(new EventIdHeader('019aa607-3a33-7f47-bb66-4223f2390a30'))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader(new IndexHeader(4));

        $store = new InMemoryStore([$message1, $message2, $message3, $message4]);

        $store->clear();

        $stream = $store->load();

        self::assertSame([], $stream->toList());
    }
}
