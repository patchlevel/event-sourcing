<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\StoreAdapter;

use Closure;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Repository\StoreAdapter\DefaultStoreAdapter;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultStoreAdapter::class)]
final class DefaultStoreAdapterTest extends TestCase
{
    public function testLoad(): void
    {
        $archived = $this->message('profile-1', 1)->withHeader(new ArchivedHeader());
        $message = $this->message('profile-1', 2);
        $otherStream = $this->message('profile-2', 1);

        $adapter = new DefaultStoreAdapter(new InMemoryStore([$archived, $message, $otherStream]));

        $loaded = $adapter->load('profile-1')->toList();

        self::assertCount(1, $loaded);
        self::assertSame($message->event(), $loaded[0]->event());
        self::assertSame(2, $loaded[0]->header(PlayheadHeader::class)->playhead);
    }

    public function testLoadFromPlayhead(): void
    {
        $stream = new Stream([]);

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new FromPlayheadCriterion(5),
            ))
            ->willReturn($stream);

        $adapter = new DefaultStoreAdapter($store);

        self::assertSame($stream, $adapter->load('profile-1', 5));
    }

    public function testHas(): void
    {
        $adapter = new DefaultStoreAdapter(new InMemoryStore([
            $this->message('profile-1', 1)->withHeader(new ArchivedHeader()),
        ]));

        self::assertTrue($adapter->has('profile-1'));
        self::assertFalse($adapter->has('profile-2'));
    }

    public function testSave(): void
    {
        $message1 = $this->message('profile-1', 1);
        $message2 = $this->message('profile-1', 2);

        $store = $this->createMock(Store::class);
        $store->expects($this->never())->method('transactional');
        $store->expects($this->never())->method('archive');
        $store->expects($this->once())->method('save')->with($message1, $message2);

        $adapter = new DefaultStoreAdapter($store);
        $adapter->save('profile-1', $message1, $message2);
    }

    public function testSaveWithStreamStartArchivesPreviousMessages(): void
    {
        $message3 = $this->message('profile-1', 3);
        $message4 = $this->message('profile-1', 4)->withHeader(new StreamStartHeader());

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (Closure $closure) => $closure());
        $store->expects($this->once())->method('save')->with($message3, $message4);
        $store
            ->expects($this->once())
            ->method('archive')
            ->with(new Criteria(
                new StreamCriterion('profile-1'),
                new ToPlayheadCriterion(4),
            ));

        $adapter = new DefaultStoreAdapter($store);
        $adapter->save('profile-1', $message3, $message4);
    }

    public function testSaveWithStreamStartInMemory(): void
    {
        $store = new InMemoryStore([
            $this->message('profile-1', 1),
            $this->message('profile-1', 2),
            $this->message('profile-2', 1),
        ]);

        $adapter = new DefaultStoreAdapter($store);
        $adapter->save('profile-1', $this->message('profile-1', 3)->withHeader(new StreamStartHeader()));

        $messages = $store->load()->toList();

        self::assertCount(4, $messages);
        self::assertTrue($messages[0]->hasHeader(ArchivedHeader::class));
        self::assertTrue($messages[1]->hasHeader(ArchivedHeader::class));
        self::assertFalse($messages[2]->hasHeader(ArchivedHeader::class));
        self::assertFalse($messages[3]->hasHeader(ArchivedHeader::class));

        $loaded = $adapter->load('profile-1')->toList();

        self::assertCount(1, $loaded);
        self::assertSame(3, $loaded[0]->header(PlayheadHeader::class)->playhead);
    }

    /** @param positive-int $playhead */
    private function message(string $streamName, int $playhead): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new StreamNameHeader($streamName))
            ->withHeader(new PlayheadHeader($playhead));
    }
}
