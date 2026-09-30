<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Generator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function usleep;

/**
 * Loads the messages from the store.
 *
 * With a filter, only the events the subscriptions are interested in are loaded.
 *
 * With gap detection, gaps in the stream are retried inside the detection window.
 * Combined with a filter, a jump in the index is expected. Before loading, the loader checks once
 * if the index range up to the current last index has no holes. In this case no gap can occur and
 * the stream is loaded up to this index without further checks. Otherwise, each skipped index range
 * is checked against the store, but only inside the detection window.
 */
final class StoreMessageLoader implements MessageLoader
{
    public function __construct(
        private readonly Store $store,
        private readonly MessageFilter|null $filter = null,
        private readonly GapDetection|null $gapDetection = null,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        $events = $this->filter?->events($subscriptions) ?? [];

        if ($this->gapDetection !== null) {
            return new GeneratorStream(
                $this->generator($this->gapDetection, $startIndex, $events),
            );
        }

        $criteria = new Criteria(new FromIndexCriterion($startIndex));

        if ($events !== []) {
            $criteria = $criteria->add(new EventsCriterion($events));
        }

        return $this->store->load($criteria);
    }

    public function lastIndex(): int
    {
        $stream = $this->store->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }

    /**
     * @param list<string> $events
     *
     * @return Generator<int, Message>
     */
    private function generator(GapDetection $gapDetection, int $lastIndex, array $events, int $retry = 0): Generator
    {
        $criteria = new Criteria(new FromIndexCriterion($lastIndex));
        $checkGaps = true;

        if ($events !== []) {
            $criteria = $criteria->add(new EventsCriterion($events));

            // the last index has to be fetched before counting, so that events written in between cannot be missed
            $maxIndex = $this->lastIndex();

            if ($maxIndex <= $lastIndex) {
                return;
            }

            if ($this->hasNoHoles($lastIndex, $maxIndex)) {
                $criteria = $criteria->add(new ToIndexCriterion($maxIndex + 1));
                $checkGaps = false;
            }
        }

        $stream = $this->store->load($criteria);

        foreach ($stream as $message) {
            $index = $stream->index();

            if ($index === null) {
                throw new UnexpectedError('Stream index is null, this should not happen.');
            }

            if (
                $checkGaps
                && $index !== $lastIndex + 1
                && $gapDetection->inWindow($message)
                && $this->hasGap($lastIndex, $index, $events)
            ) {
                $sleep = $gapDetection->retry($retry);

                if ($sleep !== null) {
                    $stream->close();
                    usleep($sleep * 1000);

                    yield from $this->generator($gapDetection, $lastIndex, $events, $retry + 1);

                    return;
                }

                // the gap is accepted, check again if the rest of the range has no holes
                if ($events !== []) {
                    $stream->close();

                    yield $index => $message;
                    yield from $this->generator($gapDetection, $index, $events);

                    return;
                }
            }

            $retry = 0;
            $lastIndex = $index;

            yield $index => $message;
        }

        $stream->close();
    }

    /**
     * All indexes up to the max index are written and committed, so a later written event
     * can only get a higher index. Loading up to the max index cannot skip any event.
     */
    private function hasNoHoles(int $fromIndex, int $maxIndex): bool
    {
        $range = new Criteria(
            new FromIndexCriterion($fromIndex),
            new ToIndexCriterion($maxIndex + 1),
        );

        return $this->store->count($range) === $maxIndex - $fromIndex;
    }

    /** @param list<string> $events */
    private function hasGap(int $fromIndex, int $toIndex, array $events): bool
    {
        if ($events === []) {
            return true;
        }

        $range = new Criteria(
            new FromIndexCriterion($fromIndex),
            new ToIndexCriterion($toIndex),
        );

        // an index in between is missing, it could still be written with an event we are interested in
        if ($this->store->count($range) !== $toIndex - $fromIndex - 1) {
            return true;
        }

        // all indexes exist, but one of them matches the filter: it was written after the stream was loaded
        return $this->store->count($range->add(new EventsCriterion($events))) !== 0;
    }
}
