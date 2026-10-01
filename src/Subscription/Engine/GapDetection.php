<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use DateInterval;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Psr\Clock\ClockInterface;

/**
 * Configures how the StoreMessageLoader detects and resolves gaps in the stream.
 */
final class GapDetection
{
    /** @param list<int> $retriesInMs in milliseconds */
    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly array $retriesInMs = [0, 5, 50, 500],
        private readonly DateInterval|null $detectionWindow = new DateInterval('PT5M'),
    ) {
    }

    /** Returns the time to sleep in milliseconds before the retry, or null if the gap should be accepted. */
    public function retry(int $attempt): int|null
    {
        return $this->retriesInMs[$attempt] ?? null;
    }

    /** Messages outside the window are old enough that a gap before them is accepted as permanent. */
    public function inWindow(Message $message): bool
    {
        if ($this->detectionWindow === null) {
            return true;
        }

        if ($message->hasHeader(RecordedOnHeader::class)) {
            return $message->header(RecordedOnHeader::class)->recordedOn > $this->clock->now()->sub($this->detectionWindow);
        }

        if ($message->hasHeader(AggregateHeader::class)) {
            return $message->header(AggregateHeader::class)->recordedOn > $this->clock->now()->sub($this->detectionWindow);
        }

        return true;
    }
}
