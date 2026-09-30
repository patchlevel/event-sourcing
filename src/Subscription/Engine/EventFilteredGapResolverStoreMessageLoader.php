<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use DateInterval;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

use function array_values;
use function usleep;

/**
 * Combines the EventFilteredStoreMessageLoader and the GapResolverStoreMessageLoader.
 *
 * Only the events the subscribers are interested in are loaded. Because of the filter,
 * a jump in the index is expected. Before loading, the loader checks once if the index range
 * up to the current last index has no holes. In this case no gap can occur and the stream is loaded
 * up to this index without further checks. Otherwise, each skipped index range is checked
 * against the store, but only inside the detection window.
 */
final class EventFilteredGapResolverStoreMessageLoader implements MessageLoader
{
    /** @param list<int> $retriesInMs in milliseconds */
    public function __construct(
        private readonly Store $store,
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly array $retriesInMs = [0, 5, 50, 500],
        private readonly DateInterval|null $detectionWindow = new DateInterval('PT5M'),
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        return new GeneratorStream(
            $this->generator($startIndex, $this->events($subscriptions)),
        );
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
    private function generator(int $lastIndex, array $events, int $retry = 0): Generator
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
                && $this->inDetectionWindow($message)
                && $this->hasGap($lastIndex, $index, $events)
            ) {
                $sleep = $this->retriesInMs[$retry] ?? null;

                if ($sleep !== null) {
                    $stream->close();
                    usleep($sleep * 1000);

                    yield from $this->generator($lastIndex, $events, $retry + 1);

                    return;
                }

                // the gap is accepted, check again if the rest of the range has no holes
                if ($events !== []) {
                    $stream->close();

                    yield $index => $message;
                    yield from $this->generator($index, $events);

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

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    private function events(array $subscriptions): array
    {
        $eventNames = [];

        foreach ($subscriptions as $subscription) {
            $subscriber = $this->subscriberRepository->get($subscription->id());

            if (!$subscriber instanceof MetadataSubscriberAccessor) {
                return [];
            }

            foreach ($subscriber->events() as $event) {
                if ($event === '*') {
                    return [];
                }

                $metadata = $this->eventMetadataFactory->metadata($event);

                $eventNames[$metadata->name] = $metadata->name;

                foreach ($metadata->aliases as $alias) {
                    $eventNames[$alias] = $alias;
                }
            }
        }

        return array_values($eventNames);
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
