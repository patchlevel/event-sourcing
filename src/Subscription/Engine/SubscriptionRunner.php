<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Generator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionProcessed;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_chunk;
use function array_keys;
use function array_merge;
use function array_slice;
use function array_values;
use function count;
use function ksort;
use function max;
use function min;
use function sprintf;

/** @internal */
final class SubscriptionRunner
{
    /** How many messages of its inbox a partition processes at most in one run without a limit. */
    private const INBOX_LIMIT = 1000;

    /** How many idle partitions a main subscription removes at most in one run. */
    private const IDLE_PARTITIONS_LIMIT = 1000;

    /** How many indices are loaded with one query, far below the parameter limits of the databases. */
    private const INDEX_CHUNK = 1000;

    public function __construct(
        private readonly MessageLoader $messageLoader,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly MessageProcessor $messageProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function process(array $subscriptions, int|null $limit, bool $boot = false): ProcessedResult
    {
        $surviving = [];

        foreach ($subscriptions as $subscription) {
            if ($this->subscriberRepository->get($subscription->subscriberId()) === null) {
                $this->logger?->debug(sprintf(
                    'Subscription Engine: Subscriber for "%s" not found, skipped.',
                    $subscription->id(),
                ));

                continue;
            }

            $surviving[] = $subscription;
        }

        // the main subscriptions of the partitions, loaded once for the whole group
        $mains = $this->mains($surviving);
        $surviving = $this->withoutStoppedSubscribers($surviving, $mains);

        if (count($surviving) > 1) {
            $separated = $this->processBatchesSeparately($surviving, $limit, $boot);

            if ($separated !== null) {
                return $separated;
            }
        }

        $members = new SubscriptionCollection($surviving);

        if (count($members) === 0) {
            return ProcessedResult::empty();
        }

        $inboxLimit = max(1, $limit ?? self::INBOX_LIMIT);
        $inboxes = $this->loadInboxes($members, $inboxLimit);
        $working = $this->workingMembers($members, $inboxes);
        $router = new PartitionRouter($this->subscriptionManager, $this->logger);
        $this->removeIdlePartitions($working);

        /** @var list<Error> $errors */
        $errors = [];
        $stream = null;
        $messageCounter = 0;
        $lastIndex = null;
        /** @var array<int, true> $loaded */
        $loaded = [];
        /** @var array<string, int> $lastHandled subscription id => index of the last message it handled */
        $lastHandled = [];
        /** @var list<Subscription> $stopped stopped by a listener for the rest of the run */
        $stopped = [];
        $limitReached = false;

        if (count($working) > 0) {
            [$startIndex, $endIndex] = $this->range($working, $inboxes);
            $indices = $this->inboxIndices($working, $inboxes);
            // only partitions, then the limit is already applied to every single inbox
            $onlyPartitions = $endIndex !== null;

            try {
                $stream = $indices !== null ? $this->loadIndices($indices) : null;

                if ($stream === null) {
                    $this->logger?->debug(sprintf(
                        'Subscription Engine: Event stream is processed from position %s.',
                        $startIndex ?? 'beginning',
                    ));

                    $stream = $this->messageLoader->load($startIndex, $working->toArray());
                }

                foreach ($stream as $index => $message) {
                    // a partition must not read past its inbox, the main subscription may not have routed these messages yet
                    if ($endIndex !== null && $index > $endIndex) {
                        break;
                    }

                    $messageCounter++;
                    $lastIndex = $index;
                    $loaded[$index] = true;

                    foreach ($working as $member) {
                        if ($member->position() >= $index) {
                            continue;
                        }

                        if ($member->isPartition() && !$inboxes[$member->id()]->contains($index)) {
                            continue;
                        }

                        $error = $this->messageProcessor->process($index, $message, $member, $router);

                        if ($error === null) {
                            $lastHandled[$member->id()] = $index;

                            continue;
                        }

                        $working->remove($member);
                        $members->remove($member);

                        if ($error instanceof ProcessingStopped) {
                            $stopped[] = $member;
                        } else {
                            $errors[] = $error;
                        }

                        if (count($working) === 0) {
                            break 2;
                        }
                    }

                    // the inbox of every partition is already limited, a shared limit would starve the others
                    if (!$onlyPartitions && $limit !== null && $messageCounter >= $limit) {
                        $this->logger?->info(sprintf(
                            'Subscription Engine: Message limit (%d) reached, finish processing.',
                            $limit,
                        ));

                        $limitReached = true;

                        break;
                    }
                }
            } finally {
                $stream?->close();
                // the routed messages are flushed in any case, so their partitions have to exist as well
                $router->createPartitions();
            }
        }

        if ($lastIndex !== null) {
            foreach ($working as $member) {
                $errors = array_merge($errors, $this->processed($member, $lastIndex));
            }
        }

        // a stopped subscription only gets as far as the messages it handled, a batch must not move it further
        foreach ($stopped as $member) {
            if (!isset($lastHandled[$member->id()])) {
                continue;
            }

            $errors = array_merge($errors, $this->processed($member, $lastHandled[$member->id()]));
        }

        foreach ([...$working, ...$stopped] as $member) {
            if ($member->isPartition()) {
                // also without any loaded message, then every message of the inbox was removed from the store
                $this->finishInbox($member, $inboxes[$member->id()], $loaded);
            } elseif ($lastIndex === null) {
                continue;
            }

            $this->subscriptionManager->update($member);
        }

        $inboxFull = $this->hasFullInbox($members, $inboxes, $inboxLimit);

        if (!$limitReached && !$inboxFull) {
            $this->finishMembers($members, $mains, $boot);
        }

        return new ProcessedResult(
            $messageCounter,
            !$limitReached && !$inboxFull,
            $errors,
        );
    }

    /**
     * The main subscription removes the idle partitions of its subscriber before it routes, while it holds its lock.
     * So it never routes into a partition that is removed at the same time, a removed one is created again.
     */
    private function removeIdlePartitions(SubscriptionCollection $working): void
    {
        foreach ($working as $member) {
            if ($member->isPartition()) {
                continue;
            }

            $idleSeconds = $this->subscriberRepository->get($member->subscriberId())?->metadata()->partition?->removeIdleAfter;

            if ($idleSeconds === null) {
                continue;
            }

            $removed = $this->subscriptionManager->removeIdlePartitions(
                $member->subscriberId(),
                $idleSeconds,
                self::IDLE_PARTITIONS_LIMIT,
            );

            if ($removed === 0) {
                continue;
            }

            $this->logger?->info(sprintf(
                'Subscription Engine: %d idle partitions of subscriber "%s" removed.',
                $removed,
                $member->subscriberId(),
            ));
        }
    }

    /** @return list<Error> */
    private function processed(Subscription $subscription, int $lastIndex): array
    {
        $event = new OnSubscriptionProcessed($subscription, $lastIndex);
        $this->eventDispatcher->dispatch($event);

        return $event->errors;
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return array<string, Subscription> subscriber id => main subscription
     */
    private function mains(array $subscriptions): array
    {
        $subscriberIds = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription->isPartition()) {
                continue;
            }

            $subscriberIds[$subscription->subscriberId()] = true;
        }

        if ($subscriberIds === []) {
            return [];
        }

        $mains = [];

        foreach (
            $this->subscriptionManager->find(
                new SubscriptionCriteria(ids: array_keys($subscriberIds), partitions: false),
            ) as $main
        ) {
            $mains[$main->id()] = $main;
        }

        return $mains;
    }

    /**
     * A main subscription in error stops its whole subscriber, e.g. after the circuit breaker detected a systemic
     * failure. Its partitions wait without being touched until the main subscription is retried.
     *
     * @param list<Subscription>          $subscriptions
     * @param array<string, Subscription> $mains
     *
     * @return list<Subscription>
     */
    private function withoutStoppedSubscribers(array $subscriptions, array $mains): array
    {
        $running = [];

        foreach ($subscriptions as $subscription) {
            $main = $subscription->isPartition() ? $mains[$subscription->subscriberId()] ?? null : null;

            if ($main !== null && ($main->isError() || $main->isFailed())) {
                $this->logger?->debug(sprintf(
                    'Subscription Engine: Partition "%s" waits, its main subscription is in error.',
                    $subscription->id(),
                ));

                continue;
            }

            $running[] = $subscription;
        }

        return $running;
    }

    /**
     * Batches of a group must not overlap. A batch often holds a resource like a database transaction from begin to
     * flush, and overlapping transactions on one connection are a stack: rolling back one batch would roll back the
     * innermost, so another batch would lose its writes while its position moves on. So every subscription with
     * batching runs alone, one after the other, while the others of the group are still processed together.
     * They all stay in the claimed transaction, so the writes of the group are still flushed together.
     *
     * @param list<Subscription> $subscriptions
     */
    private function processBatchesSeparately(array $subscriptions, int|null $limit, bool $boot): ProcessedResult|null
    {
        $batched = [];
        $others = [];

        foreach ($subscriptions as $subscription) {
            if ($this->subscriberRepository->get($subscription->subscriberId())?->metadata()->batch !== null) {
                $batched[] = $subscription;
            } else {
                $others[] = $subscription;
            }
        }

        if ($batched === []) {
            return null;
        }

        $results = [];

        if ($others !== []) {
            $results[] = $this->process($others, $limit, $boot);
        }

        foreach ($batched as $subscription) {
            $results[] = $this->process([$subscription], $limit, $boot);
        }

        return ProcessedResult::merge($results);
    }

    /**
     * @param positive-int $limit
     *
     * @return array<string, PartitionInbox> partition id => its inbox
     */
    private function loadInboxes(SubscriptionCollection $members, int $limit): array
    {
        $afterIndex = [];

        foreach ($members as $member) {
            if (!$member->isPartition()) {
                continue;
            }

            $afterIndex[$member->id()] = $member->position() ?? -1;
        }

        // one query for the inboxes of the whole group
        $indices = $this->subscriptionManager->inbox($afterIndex, $limit);
        $inboxes = [];

        foreach ($afterIndex as $subscriptionId => $index) {
            $inboxes[$subscriptionId] = new PartitionInbox($indices[$subscriptionId] ?? []);
        }

        return $inboxes;
    }

    /** @param array<string, PartitionInbox> $inboxes */
    private function workingMembers(SubscriptionCollection $members, array $inboxes): SubscriptionCollection
    {
        $working = [];

        foreach ($members as $member) {
            if ($member->isPartition() && $inboxes[$member->id()]->isEmpty()) {
                continue;
            }

            $working[] = $member;
        }

        return new SubscriptionCollection($working);
    }

    /**
     * A partition reads only the range of its inbox. The range is unbounded as soon as a subscription
     * reads the whole stream.
     *
     * @param array<string, PartitionInbox> $inboxes
     *
     * @return array{int|null, int|null}
     */
    private function range(SubscriptionCollection $working, array $inboxes): array
    {
        $startIndex = null;
        $endIndex = null;
        $first = true;
        $bounded = true;

        foreach ($working as $member) {
            if ($member->isPartition()) {
                $inbox = $inboxes[$member->id()];
                $start = $inbox->first() - 1;
                $endIndex = max($endIndex ?? 0, $inbox->last());
            } else {
                $start = $member->position();
                $bounded = false;
            }

            if ($first) {
                $startIndex = $start;
                $first = false;

                continue;
            }

            if ($startIndex === null || $start === null) {
                $startIndex = null;

                continue;
            }

            $startIndex = min($startIndex, $start);
        }

        return [$startIndex, $bounded ? $endIndex : null];
    }

    /**
     * Only the messages of the inboxes are loaded if the loader and the store can do it,
     * otherwise the partitions read the range of their inboxes and skip the other messages.
     *
     * @param non-empty-list<int> $indices
     */
    private function loadIndices(array $indices): Stream|null
    {
        if (!$this->messageLoader instanceof IndexMessageLoader) {
            return null;
        }

        $this->logger?->debug(sprintf('Subscription Engine: %d messages of the inbox are processed.', count($indices)));

        // the inboxes of a whole group can hold far more indices than a query may have parameters
        $chunks = array_chunk($indices, self::INDEX_CHUNK);

        try {
            $first = $this->messageLoader->loadIndices($chunks[0]);
        } catch (UnsupportedCriterion) {
            return null;
        }

        if (count($chunks) === 1) {
            return $first;
        }

        return new Stream($this->loadChunks($this->messageLoader, $first, array_slice($chunks, 1)));
    }

    /**
     * Loads the next chunk only when the previous one is consumed, so the messages stay in ascending order.
     *
     * @param list<non-empty-list<int>> $chunks
     *
     * @return Generator<int, Message>
     */
    private function loadChunks(IndexMessageLoader $messageLoader, Stream $stream, array $chunks): Generator
    {
        try {
            yield from $stream;

            foreach ($chunks as $chunk) {
                $stream->close();
                $stream = $messageLoader->loadIndices($chunk);

                yield from $stream;
            }
        } finally {
            $stream->close();
        }
    }

    /**
     * The indices of all inboxes, or null if a subscription has to read the stream.
     *
     * @param array<string, PartitionInbox> $inboxes
     *
     * @return non-empty-list<int>|null
     */
    private function inboxIndices(SubscriptionCollection $working, array $inboxes): array|null
    {
        $indices = [];

        foreach ($working as $member) {
            if (!$member->isPartition()) {
                return null;
            }

            foreach ($inboxes[$member->id()]->indices() as $index) {
                $indices[$index] = $index;
            }
        }

        if ($indices === []) {
            return null;
        }

        ksort($indices);

        return array_values($indices);
    }

    /**
     * A partition never moves past its inbox, otherwise it would skip messages the main subscription routes later.
     * The store clears the inbox up to the position when the partition is updated, a failed one included.
     *
     * Messages of the inbox that were not loaded no longer exist in the store, e.g. because their stream was removed.
     * If the partition processed every loaded message, it moves past them, otherwise it would wait for them forever.
     *
     * @param array<int, true> $loaded indices of the loaded messages
     */
    private function finishInbox(Subscription $partition, PartitionInbox $inbox, array $loaded): void
    {
        $position = $partition->position() ?? -1;

        if ($position >= $inbox->last()) {
            $partition->changePosition($inbox->last());

            return;
        }

        if ($partition->isError() || $partition->isFailed()) {
            return;
        }

        foreach ($inbox->indices() as $index) {
            // a loaded message that was not processed, e.g. skipped, has to stay in the inbox
            if ($index > $position && isset($loaded[$index])) {
                return;
            }
        }

        $this->logger?->warning(sprintf(
            'Subscription Engine: Partition "%s" skips messages of its inbox up to %d, they no longer exist.',
            $partition->id(),
            $inbox->last(),
        ));

        $partition->changePosition($inbox->last());
    }

    /** @param array<string, PartitionInbox> $inboxes */
    private function hasFullInbox(SubscriptionCollection $members, array $inboxes, int $limit): bool
    {
        foreach ($members as $member) {
            if ($member->isPartition() && $inboxes[$member->id()]->count() >= $limit) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, Subscription> $mains */
    private function finishMembers(SubscriptionCollection $members, array $mains, bool $boot): void
    {
        foreach ($members as $member) {
            if ($boot) {
                if ($member->status() !== Status::Booting) {
                    continue;
                }

                // the main subscription may still route messages into the inbox of a booting partition
                if ($member->isPartition() && ($mains[$member->subscriberId()] ?? null)?->isBooting()) {
                    continue;
                }

                if ($member->runMode() === RunMode::Once && $this->isRoutingFinished($member, $mains)) {
                    $member->finished();

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                        $member->id(),
                    ));
                } else {
                    $member->active();

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" has been set to active after booting.',
                        $member->id(),
                    ));
                }

                $this->subscriptionManager->update($member);

                continue;
            }

            if ($member->status() !== Status::Active || $member->runMode() !== RunMode::Once) {
                continue;
            }

            if (!$this->isRoutingFinished($member, $mains)) {
                continue;
            }

            $member->finished();
            $this->subscriptionManager->update($member);

            $this->logger?->info(sprintf(
                'Subscription Engine: Subscription "%s" run only once and has been set to finished.',
                $member->id(),
            ));
        }
    }

    /**
     * A partition is only done when its main subscription has routed all messages.
     *
     * @param array<string, Subscription> $mains
     */
    private function isRoutingFinished(Subscription $subscription, array $mains): bool
    {
        if (!$subscription->isPartition()) {
            return true;
        }

        return ($mains[$subscription->subscriberId()] ?? null)?->isFinished() ?? true;
    }
}
