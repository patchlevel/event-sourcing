<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionProcessed;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_merge;
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

        $members = new SubscriptionCollection($surviving);

        if (count($members) === 0) {
            return ProcessedResult::empty();
        }

        $inboxLimit = max(1, $limit ?? self::INBOX_LIMIT);
        $inboxes = $this->loadInboxes($members, $inboxLimit);
        $working = $this->workingMembers($members, $inboxes);
        $router = new PartitionRouter($this->subscriptionManager, $this->logger);

        /** @var list<Error> $errors */
        $errors = [];
        $stream = null;
        $messageCounter = 0;
        $lastIndex = null;
        $limitReached = false;

        if (count($working) > 0) {
            [$startIndex, $endIndex] = $this->range($working, $inboxes);
            $indices = $this->inboxIndices($working, $inboxes);

            try {
                if ($indices !== null) {
                    // only partitions, so only the messages of their inboxes have to be loaded
                    $this->logger?->debug(sprintf(
                        'Subscription Engine: %d messages of the inbox are processed.',
                        count($indices),
                    ));

                    $stream = $this->messageLoader->loadIndices($indices);
                } else {
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

                    foreach ($working as $member) {
                        if ($member->position() >= $index) {
                            continue;
                        }

                        if ($member->isPartition() && !$inboxes[$member->id()]->contains($index)) {
                            continue;
                        }

                        $error = $this->messageProcessor->process($index, $message, $member, $router);

                        if (!$error) {
                            continue;
                        }

                        $errors[] = $error;
                        $working->remove($member);
                        $members->remove($member);

                        if (count($working) === 0) {
                            break 2;
                        }
                    }

                    if ($limit !== null && $messageCounter >= $limit) {
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
            }
        }

        if ($lastIndex !== null) {
            foreach ($working as $member) {
                $event = new OnSubscriptionProcessed($member, $lastIndex);
                $this->eventDispatcher->dispatch($event);
                $errors = array_merge($errors, $event->errors);
            }

            foreach ($working as $member) {
                if ($member->isPartition()) {
                    $this->finishInbox($member, $inboxes[$member->id()]);
                }

                $this->subscriptionManager->update($member);
            }
        }

        $inboxFull = $this->hasFullInbox($members, $inboxes, $inboxLimit);

        if (!$limitReached && !$inboxFull) {
            $this->finishMembers($members, $boot);
        }

        return new ProcessedResult(
            $messageCounter,
            !$limitReached && !$inboxFull && !$router->hasRouted(),
            $errors,
        );
    }

    /**
     * @param positive-int $limit
     *
     * @return array<string, PartitionInbox> partition id => its inbox
     */
    private function loadInboxes(SubscriptionCollection $members, int $limit): array
    {
        $inboxes = [];

        foreach ($members as $member) {
            if (!$member->isPartition()) {
                continue;
            }

            $inboxes[$member->id()] = new PartitionInbox(
                $this->subscriptionManager->inbox($member->id(), $member->position() ?? -1, $limit),
            );
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
     * Everything up to the new position has been processed and is removed from the inbox.
     */
    private function finishInbox(Subscription $partition, PartitionInbox $inbox): void
    {
        $position = $partition->position();

        if ($position === null) {
            return;
        }

        $end = $inbox->last();

        if ($position > $end) {
            $partition->changePosition($end);
            $position = $end;
        }

        $this->subscriptionManager->removeFromInbox($partition->id(), $position);
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

    private function finishMembers(SubscriptionCollection $members, bool $boot): void
    {
        foreach ($members as $member) {
            if ($boot) {
                if ($member->status() !== Status::Booting) {
                    continue;
                }

                // the main subscription may still route messages into the inbox of a booting partition
                if ($member->isPartition() && $this->main($member)?->isBooting()) {
                    continue;
                }

                if ($member->runMode() === RunMode::Once && $this->isRoutingFinished($member)) {
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

            if (!$this->isRoutingFinished($member)) {
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

    /** A partition is only done when its main subscription has routed all messages. */
    private function isRoutingFinished(Subscription $subscription): bool
    {
        if (!$subscription->isPartition()) {
            return true;
        }

        return $this->main($subscription)?->isFinished() ?? true;
    }

    private function main(Subscription $partition): Subscription|null
    {
        return $this->subscriptionManager->find(
            new SubscriptionCriteria(ids: [$partition->subscriberId()], partitions: false),
        )[0] ?? null;
    }
}
