<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Subscription\Partitioner\BucketPartitioner;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function array_values;
use function count;
use function range;
use function sort;

#[CoversClass(BucketPartitioner::class)]
final class BucketPartitionerTest extends TestCase
{
    public function testSpreadsTheKeysOverTheBuckets(): void
    {
        $partitioner = new BucketPartitioner(new StreamPartitioner(), 4);

        $keys = [];

        foreach (range(1, 200) as $i) {
            $keys[] = $partitioner($this->message('profile-' . $i));
        }

        self::assertSame(['0', '1', '2', '3'], $this->sorted(array_unique($keys)));
    }

    public function testSameKeySameBucket(): void
    {
        $partitioner = new BucketPartitioner(new StreamPartitioner(), 16);

        self::assertSame($partitioner($this->message('profile-1')), $partitioner($this->message('profile-1')));
    }

    private function message(string $stream): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new StreamNameHeader($stream));
    }

    /**
     * @param array<string> $keys
     *
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        $keys = array_values($keys);
        sort($keys);

        self::assertGreaterThan(0, count($keys));

        return $keys;
    }
}
