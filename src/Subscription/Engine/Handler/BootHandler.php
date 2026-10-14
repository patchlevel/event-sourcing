<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

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
        /** @var list<ProcessedResult> $results */
        $results = $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Booting],
            ),
            $command->partitionsPerClaim,
            $command->subscriptionsPerClaim,
            fn (array $subscriptions): ProcessedResult => $this->runner->process(
                $subscriptions,
                $command->limit,
                boot: true,
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

        return ProcessedResult::merge($results);
    }
}
