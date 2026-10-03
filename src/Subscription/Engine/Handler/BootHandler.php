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
        $results = $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Booting],
            ),
            fn (Subscription $subscription): ProcessedResult => $this->runner->process(
                [$subscription],
                $command->limit,
                boot: true,
            ),
            static fn (Subscription $subscription, Throwable $e): ProcessedResult => new ProcessedResult(
                0,
                false,
                [new Error($subscription->id(), $e->getMessage(), $e)],
            ),
        );

        return ProcessedResult::merge($results);
    }
}
