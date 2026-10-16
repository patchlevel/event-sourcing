<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_map;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Reactivate>
 */
final class ReactivateHandler implements Handler
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
        // partitions are claimed in groups, a subscriber can have many of them
        $results = $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [
                    Status::Error,
                    Status::Failed,
                    Status::Detached,
                    Status::Paused,
                    Status::Finished,
                ],
            ),
            new ClaimGroups(partitions: self::PARTITIONS_PER_CLAIM),
            function (array $subscriptions): Result {
                foreach ($subscriptions as $subscription) {
                    $this->reactivate($subscription);
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

    private function reactivate(Subscription $subscription): void
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber for "%s" not found, skipped.',
                    $subscription->id(),
                ),
            );

            return;
        }

        $error = $subscription->subscriptionError();

        if ($error) {
            $subscription->doRetry();
            $subscription->resetRetry();
        } else {
            $subscription->active();
        }

        $this->subscriptionManager->update($subscription);

        $this->logger?->info(sprintf(
            'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
            $subscriber::class,
            $subscription->id(),
        ));
    }
}
