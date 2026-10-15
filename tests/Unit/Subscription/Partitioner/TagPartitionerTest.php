<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Subscription\Partitioner\TagNotUnique;
use Patchlevel\EventSourcing\Subscription\Partitioner\TagPartitioner;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagPartitioner::class)]
#[CoversClass(TagNotUnique::class)]
final class TagPartitionerTest extends TestCase
{
    public function testTagWithThePrefix(): void
    {
        $partitioner = new TagPartitioner('course:');

        self::assertSame('course:42', $partitioner($this->message(['student:1', 'course:42'])));
    }

    public function testNoTagWithThePrefix(): void
    {
        $this->expectException(TagNotUnique::class);

        (new TagPartitioner('course:'))($this->message(['student:1']));
    }

    public function testNoTags(): void
    {
        $this->expectException(TagNotUnique::class);

        (new TagPartitioner('course:'))(Message::create(new ProfileVisited(ProfileId::fromString('1'))));
    }

    public function testSeveralTagsWithThePrefix(): void
    {
        $this->expectException(TagNotUnique::class);
        $this->expectExceptionMessage('course:1, course:2');

        (new TagPartitioner('course:'))($this->message(['course:1', 'course:2']));
    }

    /** @param list<string> $tags */
    private function message(array $tags): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new TagsHeader($tags));
    }
}
