<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use DateInterval;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

use function usleep;

final class GapResolverStoreMessageLoader implements MessageLoader
{
    /** @param list<int> $retriesInMs in milliseconds */
    public function __construct(
        private readonly Store $store,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly array $retriesInMs = [0, 5, 50, 500],
        private readonly DateInterval|null $detectionWindow = new DateInterval('PT5M'),
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        return new GeneratorStream(
            $this->generator($startIndex),
        );
    }

    public function lastIndex(): int
    {
        $stream = $this->store->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }

    /** @return Generator<int, Message> */
    private function generator(int $startIndex, int $retry = 0): Generator
    {
        $currentIndex = $startIndex + 1;
        $stream = $this->store->load(new Criteria(new FromIndexCriterion($startIndex)));

        foreach ($stream as $message) {
            if ($currentIndex !== $stream->index() && $this->inDetectionWindow($message)) {
                $sleep = $this->retriesInMs[$retry] ?? null;

                if ($sleep !== null) {
                    $stream->close();
                    usleep($sleep * 1000);

                    yield from $this->generator($currentIndex - 1, $retry + 1);

                    break;
                }
            }

            $retry = 0;
            $currentIndex++;

            yield $stream->index() => $message;
        }

        $stream->close();
    }

    private function inDetectionWindow(Message $message): bool
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
