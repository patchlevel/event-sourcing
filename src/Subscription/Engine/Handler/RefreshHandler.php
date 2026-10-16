<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function array_values;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Refresh>
 */
final class RefreshHandler implements Handler
{
    private const PARTITIONS_PER_CLAIM = 100;

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        // a worker that is processing a subscription is waited for, it would overwrite the changes otherwise
        $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
            ),
            new ClaimGroups(partitions: self::PARTITIONS_PER_CLAIM),
            function (array $subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $this->refresh($subscription);
                }
            },
            wait: true,
            // only the subscriptions that change are claimed, checked again once they are claimed
            filter: fn (Subscription $subscription): bool => $this->needsRefresh($subscription),
        );

        return new Result();
    }

    private function needsRefresh(Subscription $subscription): bool
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            return false;
        }

        return $subscription->runMode() !== $subscriber->metadata()->runMode
            || $subscription->group() !== $subscriber->metadata()->group
            || (!$subscription->isPartition() && $subscription->cleanupTasks() !== $this->cleanupTasks($subscriber));
    }

    private function refresh(Subscription $subscription): void
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            return;
        }

        $changed = false;

        if ($subscription->runMode() !== $subscriber->metadata()->runMode) {
            $changed = true;
            $oldRunMode = $subscription->runMode();
            $subscription->changeRunMode($subscriber->metadata()->runMode);

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Subscription "%s" run mode changed from "%s" to "%s".',
                    $subscription->id(),
                    $oldRunMode->value,
                    $subscription->runMode()->value,
                ),
            );
        }

        if ($subscription->group() !== $subscriber->metadata()->group) {
            $changed = true;
            $oldGroup = $subscription->group();
            $subscription->changeGroup($subscriber->metadata()->group);

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Subscription "%s" group changed from "%s" to "%s".',
                    $subscription->id(),
                    $oldGroup,
                    $subscription->group(),
                ),
            );
        }

        // cleanup tasks belong to the subscriber as a whole and are only stored on its main subscription,
        // so the lazily loaded tasks of a partition are not touched at all
        $cleanupTasks = $subscription->isPartition() ? null : $this->cleanupTasks($subscriber);

        if (!$subscription->isPartition() && $subscription->cleanupTasks() !== $cleanupTasks) {
            $changed = true;
            $subscription->replaceCleanupTasks($cleanupTasks);

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Subscription "%s" cleanup tasks changed.',
                    $subscription->id(),
                ),
            );
        }

        if (!$changed) {
            return;
        }

        $this->subscriptionManager->update($subscription);
    }

    /** @return list<object>|null */
    private function cleanupTasks(MetadataSubscriberAccessor $subscriber): array|null
    {
        $method = $subscriber->cleanupMethod();

        if (!$method) {
            return null;
        }

        return array_values([...$method()]);
    }
}
