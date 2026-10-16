<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Closure;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Subscription\Partitioner\Partitioner;

/** One partition per stream, unless a test changes the key, e.g. to simulate a changed or broken partitioner. */
final class ConfigurablePartitioner implements Partitioner
{
    /** @var (Closure(Message):string)|null */
    public static Closure|null $key = null;

    public function __invoke(Message $message): string
    {
        if (self::$key !== null) {
            return (self::$key)($message);
        }

        return $message->header(StreamNameHeader::class)->streamName;
    }
}
