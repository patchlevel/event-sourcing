<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;

/** @experimental */
final class SaveResult
{
    /** @param list<Message> $messages the messages as they were stored */
    public function __construct(
        public readonly array $messages,
        public readonly Version $version,
    ) {
    }
}
