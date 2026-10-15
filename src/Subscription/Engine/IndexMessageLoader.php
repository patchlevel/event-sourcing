<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Stream;

/**
 * A message loader that can load single messages by their index. Partitions use it to read only the messages in their
 * inbox, without it they read the range of their inbox and skip the other messages.
 */
interface IndexMessageLoader extends MessageLoader
{
    /**
     * Loads only the messages with the given indices in ascending order.
     *
     * @param non-empty-list<int> $indices
     */
    public function loadIndices(array $indices): Stream;
}
