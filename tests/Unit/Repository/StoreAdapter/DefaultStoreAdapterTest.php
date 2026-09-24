<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Repository\StoreAdapter\DefaultStoreAdapter;
use Patchlevel\EventSourcing\Repository\StoreAdapter\Version;
use Patchlevel\EventSourcing\Repository\StoreAdapter\VersionConflict;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(DefaultStoreAdapter::class)]
final class DefaultStoreAdapterTest extends TestCase
{
    public function testLoad(): void
    {
        $adapter = new DefaultStoreAdapter(new InMemoryStore([
            $this->storedMessage('profile-1', 1)->withHeader(new ArchivedHeader()),
            $this->storedMessage('profile-1', 2),
            $this->storedMessage('profile-1', 3),
            $this->storedMessage('profile-2', 1),
        ]));

        $loaded = $adapter->load(Profile::metadata(), '1');

        self::assertSame(1, $loaded->playhead);
        self::assertSame([2, 3], $this->playheads($loaded->stream->toList()));
        self::assertEquals(new Version(3), $loaded->version());
    }

    public function testLoadFromPlayhead(): void
    {
        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new FromPlayheadCriterion(2),
            ))
            ->willReturn(new Stream([$this->storedMessage('profile-1', 3)]));

        $adapter = new DefaultStoreAdapter($store);

        $loaded = $adapter->load(Profile::metadata(), '1', 2);

        self::assertSame(2, $loaded->playhead);
        self::assertSame([3], $this->playheads($loaded->stream->toList()));
        self::assertEquals(new Version(3), $loaded->version());
    }

    public function testLoadEmpty(): void
    {
        $adapter = new DefaultStoreAdapter(new InMemoryStore());

        $loaded = $adapter->load(Profile::metadata(), '1', 5);

        self::assertSame(5, $loaded->playhead);
        self::assertNull($loaded->stream->current());
        self::assertEquals(new Version(5), $loaded->version());
    }

    public function testHas(): void
    {
        $adapter = new DefaultStoreAdapter(new InMemoryStore([
            $this->storedMessage('profile-1', 1)->withHeader(new ArchivedHeader()),
        ]));

        self::assertTrue($adapter->has(Profile::metadata(), '1'));
        self::assertFalse($adapter->has(Profile::metadata(), '2'));
    }

    public function testSave(): void
    {
        $store = new InMemoryStore([
            $this->storedMessage('profile-1', 1),
        ]);

        $adapter = new DefaultStoreAdapter($store);
        $result = $adapter->save(Profile::metadata(), '1', new Version(1), $this->message(), $this->message());

        self::assertEquals(new Version(3), $result->version);
        self::assertSame([2, 3], $this->playheads($result->messages));
        self::assertSame('profile-1', $result->messages[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame([1, 2, 3], $this->playheads($store->load()->toList()));
    }

    public function testSaveNewStream(): void
    {
        $adapter = new DefaultStoreAdapter(new InMemoryStore());
        $result = $adapter->save(Profile::metadata(), '1', null, $this->message());

        self::assertEquals(new Version(1), $result->version);
        self::assertSame([1], $this->playheads($result->messages));
    }

    public function testSaveWithStreamStart(): void
    {
        $store = new InMemoryStore([
            $this->storedMessage('profile-1', 1),
            $this->storedMessage('profile-1', 2),
            $this->storedMessage('profile-2', 1),
        ]);

        $adapter = new DefaultStoreAdapter($store);
        $adapter->save(
            Profile::metadata(),
            '1',
            new Version(2),
            $this->message()->withHeader(new StreamStartHeader()),
        );

        $messages = $store->load()->toList();

        self::assertCount(4, $messages);
        self::assertTrue($messages[0]->hasHeader(ArchivedHeader::class));
        self::assertTrue($messages[1]->hasHeader(ArchivedHeader::class));
        self::assertFalse($messages[2]->hasHeader(ArchivedHeader::class));
        self::assertFalse($messages[3]->hasHeader(ArchivedHeader::class));

        $loaded = $adapter->load(Profile::metadata(), '1');

        self::assertSame(2, $loaded->playhead);
        self::assertSame([3], $this->playheads($loaded->stream->toList()));
    }

    public function testSaveConflict(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('save')->willThrowException(new UniqueConstraintViolation());

        $adapter = new DefaultStoreAdapter($store);

        $this->expectException(VersionConflict::class);

        $adapter->save(Profile::metadata(), '1', new Version(1), $this->message());
    }

    private function message(): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')));
    }

    /** @param positive-int $playhead */
    private function storedMessage(string $streamName, int $playhead): Message
    {
        return $this->message()
            ->withHeader(new StreamNameHeader($streamName))
            ->withHeader(new PlayheadHeader($playhead));
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<int>
     */
    private function playheads(array $messages): array
    {
        return array_map(
            static fn (Message $message) => $message->header(PlayheadHeader::class)->playhead,
            $messages,
        );
    }
}
