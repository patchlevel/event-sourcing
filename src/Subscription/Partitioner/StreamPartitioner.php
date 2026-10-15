<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;

/** One partition per stream, e.g. per aggregate. */
final class StreamPartitioner implements Partitioner
{
    public function __invoke(Message $message): string
    {
        return $message->header(StreamNameHeader::class)->streamName;
    }
}
