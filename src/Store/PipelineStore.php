<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;

/** @deprecated use StreamableStore */
interface PipelineStore extends Store
{
    /** @return Generator<Message> */
    public function stream(int $fromIndex = 0): Generator;

    public function count(int $fromIndex = 0): int;
}
