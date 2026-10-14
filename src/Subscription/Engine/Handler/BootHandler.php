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
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
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
        private readonly SubscriberAccessorRepository $subscriberRepository,
    ) {
    }

    public function __invoke(Command $command): ProcessedResult
    {
        $criteria = new SubscriptionCriteria(
            groups: $command->groups,
            status: [Status::Booting],
            subscriberIds: $command->ids,
            shards: $command->shards,
        );

        /** @var list<ProcessedResult> $results */
        $results = $this->subscriptionManager->forEachClaimed(
            $criteria,
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

        $result = ProcessedResult::merge($results);

        if (!$result->finished || !$this->hasBootingSubscriptions($criteria)) {
            return $result;
        }

        return new ProcessedResult($result->processedMessages, false, $result->errors);
    }

    /**
     * Subscriptions can still be booting although every claimed one is finished, e.g. partitions that were discovered by
     * another worker or by a main subscription that failed afterwards. Booting is only finished when none is left.
     */
    private function hasBootingSubscriptions(SubscriptionCriteria $criteria): bool
    {
        foreach ($this->subscriptionManager->find($criteria) as $subscription) {
            if ($this->subscriberRepository->get($subscription->subscriberId()) !== null) {
                return true;
            }
        }

        return false;
    }
}
