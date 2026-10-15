<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionRunner;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Throwable;

use function array_map;

/**
 * @internal
 *
 * @implements Handler<Boot>
 */
final class BootHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriptionRunner $runner,
    ) {
    }

    public function __invoke(Command $command): ProcessedResult
    {
        $supportsPartitions = $this->subscriptionManager->supportsPartitions();
        $claimGroups = new ClaimGroups(
            $command->subscriptionsPerClaim,
            $command->partitionsPerClaim,
            // the group reads the stream from its lowest position, a larger gap would use up the limit of the others
            $command->limit ?? ClaimGroups::DEFAULT_POSITION_WINDOW,
        );

        /** @var list<ProcessedResult> $results */
        $results = $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Booting],
                // partitions without messages in their inbox have nothing to do while their main subscription boots
                pendingPartitionsOnly: $supportsPartitions,
            ),
            $claimGroups,
            fn (array $subscriptions): ProcessedResult => $this->process($subscriptions, $command),
            $this->onError(...),
        );

        if ($supportsPartitions) {
            $results = [...$results, ...$this->finishPartitions($command, $claimGroups)];
        }

        return ProcessedResult::merge($results);
    }

    /**
     * Once the main subscription finished booting, its partitions that are still booting are processed one last time,
     * so that they are set to active, or finished for run mode once, even if their inbox is already empty.
     *
     * @return list<ProcessedResult>
     */
    private function finishPartitions(Boot $command, ClaimGroups $claimGroups): array
    {
        $subscriberIds = [];

        $mains = $this->subscriptionManager->find(new SubscriptionCriteria(
            ids: $command->ids,
            groups: $command->groups,
            status: [Status::Active, Status::Finished],
            partitions: false,
        ));

        foreach ($mains as $main) {
            $subscriberIds[] = $main->id();
        }

        if ($subscriberIds === []) {
            return [];
        }

        $criteria = new SubscriptionCriteria(ids: $subscriberIds, status: [Status::Booting], partitions: true);

        if ($this->subscriptionManager->find($criteria) === []) {
            return [];
        }

        return [
            ...$this->subscriptionManager->forEachClaimedGroup(
                $criteria,
                $claimGroups,
                fn (array $subscriptions): ProcessedResult => $this->process($subscriptions, $command),
                $this->onError(...),
            ),
            // a partition held by another worker is not claimed, the next boot run checks again
            new ProcessedResult(0, false),
        ];
    }

    /** @param non-empty-list<Subscription> $subscriptions */
    private function process(array $subscriptions, Boot $command): ProcessedResult
    {
        return $this->runner->process($subscriptions, $command->limit, boot: true);
    }

    /** @param non-empty-list<Subscription> $subscriptions */
    private function onError(array $subscriptions, Throwable $e): ProcessedResult
    {
        return new ProcessedResult(
            0,
            false,
            array_map(
                static fn (Subscription $subscription): Error => new Error($subscription->id(), $e->getMessage(), $e),
                $subscriptions,
            ),
        );
    }
}
