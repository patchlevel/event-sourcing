<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;

/** Returns the partition key of a message, see {@see \Patchlevel\EventSourcing\Attribute\Partition}. */
interface Partitioner
{
    public function __invoke(Message $message): string;
}
