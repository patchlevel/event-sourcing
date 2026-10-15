<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Subscription\Partitioner\HashedPartitioner;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function hash;
use function str_repeat;
use function strlen;

#[CoversClass(HashedPartitioner::class)]
final class HashedPartitionerTest extends TestCase
{
    public function testHashesTheKey(): void
    {
        $partitioner = new HashedPartitioner(new StreamPartitioner());

        self::assertSame(hash('xxh128', 'profile-1'), $partitioner($this->message('profile-1')));
        self::assertNotSame($partitioner($this->message('ACME')), $partitioner($this->message('acme')));
        self::assertSame(32, strlen($partitioner($this->message(str_repeat('x', 1000)))));
    }

    public function testOtherAlgorithm(): void
    {
        $partitioner = new HashedPartitioner(new StreamPartitioner(), 'sha256');

        self::assertSame(hash('sha256', 'profile-1'), $partitioner($this->message('profile-1')));
    }

    private function message(string $stream): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new StreamNameHeader($stream));
    }
}
