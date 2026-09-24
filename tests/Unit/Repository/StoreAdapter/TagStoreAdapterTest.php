<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\StoreAdapter\TagStoreAdapter;
use Patchlevel\EventSourcing\Repository\StoreAdapter\Version;
use Patchlevel\EventSourcing\Repository\StoreAdapter\VersionConflict;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;

#[CoversClass(TagStoreAdapter::class)]
final class TagStoreAdapterTest extends TestCase
{
    public function testLoad(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
            $this->message('profile:2'),
            $this->message('profile:1', 'course:1'),
        ]));

        $loaded = $adapter->load(Profile::metadata(), '1');

        self::assertSame(0, $loaded->playhead);
        self::assertSame([1, 3], $this->indexes($loaded->stream->toList()));
        self::assertEquals(new Version(3), $loaded->version());
    }

    public function testLoadFromPlayhead(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
            $this->message('profile:1'),
            $this->message('profile:2'),
            $this->message('profile:1'),
        ]));

        $loaded = $adapter->load(Profile::metadata(), '1', 2);

        self::assertSame(2, $loaded->playhead);
        self::assertSame([4], $this->indexes($loaded->stream->toList()));
        self::assertEquals(new Version(4), $loaded->version());
    }

    public function testLoadSplitStream(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
            $this->message('profile:1'),
            $this->message('profile:1')->withHeader(new StreamStartHeader()),
            $this->message('profile:1'),
        ]));

        $loaded = $adapter->load(Profile::metadata(), '1');

        self::assertSame(2, $loaded->playhead);
        self::assertSame([3, 4], $this->indexes($loaded->stream->toList()));
        self::assertEquals(new Version(4), $loaded->version());
    }

    public function testLoadEmpty(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:2'),
        ]));

        $loaded = $adapter->load(Profile::metadata(), '1');

        self::assertSame(0, $loaded->playhead);
        self::assertNull($loaded->stream->current());
        self::assertEquals(new Version(0), $loaded->version());
    }

    public function testHas(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
        ]));

        self::assertTrue($adapter->has(Profile::metadata(), '1'));
        self::assertFalse($adapter->has(Profile::metadata(), '2'));
    }

    public function testSave(): void
    {
        $store = new InMemoryStore([
            $this->message('profile:1'),
            $this->message('profile:2'),
        ]);

        $adapter = new TagStoreAdapter($store);
        $result = $adapter->save(
            Profile::metadata(),
            '1',
            new Version(1),
            $this->message('course:1'),
            $this->message(),
        );

        self::assertEquals(new Version(4), $result->version);
        self::assertSame(['course:1', 'profile:1'], $result->messages[0]->header(TagsHeader::class)->tags);
        self::assertSame(['profile:1'], $result->messages[1]->header(TagsHeader::class)->tags);
        self::assertCount(4, $store->load()->toList());
    }

    public function testSaveNewStream(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:2'),
        ]));

        $result = $adapter->save(Profile::metadata(), '1', null, $this->message());

        self::assertEquals(new Version(2), $result->version);
    }

    public function testSaveNewStreamConflict(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
        ]));

        $this->expectException(VersionConflict::class);

        $adapter->save(Profile::metadata(), '1', null, $this->message());
    }

    public function testSaveConflict(): void
    {
        $adapter = new TagStoreAdapter(new InMemoryStore([
            $this->message('profile:1'),
            $this->message('profile:1'),
        ]));

        $this->expectException(VersionConflict::class);

        $adapter->save(Profile::metadata(), '1', new Version(1), $this->message());
    }

    private function message(string ...$tags): Message
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        if ($tags === []) {
            return $message;
        }

        return $message->withHeader(new TagsHeader(array_values($tags)));
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<int>
     */
    private function indexes(array $messages): array
    {
        return array_map(
            static fn (Message $message) => $message->header(IndexHeader::class)->index,
            $messages,
        );
    }
}
