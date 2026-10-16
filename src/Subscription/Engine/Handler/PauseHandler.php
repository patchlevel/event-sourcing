<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_map;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Pause>
 */
final class PauseHandler implements Handler
{
    private const PARTITIONS_PER_CLAIM = 100;

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        // partitions are claimed in groups, a subscriber can have many of them
        $results = $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [
                    Status::Active,
                    Status::Booting,
                    Status::Error,
                ],
            ),
            new ClaimGroups(partitions: self::PARTITIONS_PER_CLAIM),
            function (array $subscriptions): Result {
                foreach ($subscriptions as $subscription) {
                    $subscription->pause();
                    $this->subscriptionManager->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscription "%s" is paused.',
                        $subscription->id(),
                    ));
                }

                return new Result();
            },
            static fn (array $subscriptions, Throwable $e): Result => new Result(array_map(
                static fn (Subscription $subscription): Error => new Error($subscription->id(), $e->getMessage(), $e),
                $subscriptions,
            )),
            wait: true,
        );

        return Result::merge($results);
    }
}
