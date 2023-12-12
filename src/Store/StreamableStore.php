<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;

interface StreamableStore extends PipelineStore
{
    /** @return Generator<Message> */
    public function stream(int $fromIndex = 0): Generator;

    public function count(int $fromIndex = 0): int;
}
