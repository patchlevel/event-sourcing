<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
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
 * @implements Handler<Run>
 */
final class RunHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriptionRunner $runner,
    ) {
    }

    public function __invoke(Command $command): ProcessedResult
    {
        $claimGroups = new ClaimGroups(
            $command->subscriptionsPerClaim,
            $command->partitionsPerClaim,
            // the group reads the stream from its lowest position, a larger gap would use up the limit of the others
            $command->limit ?? ClaimGroups::DEFAULT_POSITION_WINDOW,
        );

        // only a store with an inbox has partitions, every other store does not need to know the filters
        if (!$this->subscriptionManager->supportsPartitions()) {
            return ProcessedResult::merge($this->process(
                new SubscriptionCriteria(ids: $command->ids, groups: $command->groups, status: [Status::Active]),
                $claimGroups,
                $command,
            ));
        }

        $results = $this->process(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Active],
                partitions: false,
            ),
            $claimGroups,
            $command,
        );

        // the partitions are found after the main subscriptions routed, so they process the new messages right away
        $results = [
            ...$results,
            ...$this->process(
                new SubscriptionCriteria(
                    ids: $command->ids,
                    groups: $command->groups,
                    status: [Status::Active],
                    partitions: true,
                    pendingPartitionsOnly: true,
                ),
                $claimGroups,
                $command,
            ),
        ];

        return ProcessedResult::merge($results);
    }

    /** @return list<ProcessedResult> */
    private function process(SubscriptionCriteria $criteria, ClaimGroups $claimGroups, Run $command): array
    {
        /** @var list<ProcessedResult> $results */
        $results = $this->subscriptionManager->forEachClaimedGroup(
            $criteria,
            $claimGroups,
            fn (array $subscriptions): ProcessedResult => $this->runner->process(
                $subscriptions,
                $command->limit,
            ),
            static fn (array $subscriptions, Throwable $e): ProcessedResult => new ProcessedResult(
                0,
                false,
                array_map(
                    static fn (Subscription $subscription): Error => new Error($subscription->id(), $e->getMessage(), $e),
                    $subscriptions,
                ),
            ),
        );

        return $results;
    }
}
