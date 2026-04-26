<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;

final class OnProcessingFinished
{
    public const REASON_STREAM_ENDED = 'stream-ended';
    public const REASON_LIMIT_REACHED = 'limit-reached';

    /** @param self::REASON_* $reason */
    public function __construct(
        public readonly Command $command,
        public readonly string $reason,
        public readonly int $processed,
    ) {
    }
}
