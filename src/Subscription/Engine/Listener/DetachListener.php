<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function sprintf;

/** @internal */
final class DetachListener
{
    private const PARTITIONS_PER_CLAIM = 100;

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(OnCommand $event): void
    {
        $command = $event->command;

        if (!$command instanceof Run) {
            return;
        }

        /** @var list<string> $detached */
        $detached = [];

        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Active, Status::Paused, Status::Finished],
                // partitions follow their main subscription, checking each of them would cost a claim per partition
                partitions: false,
            ),
            function (Subscription $subscription) use (&$detached): void {
                $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

                if ($subscriber) {
                    return;
                }

                $subscription->detached();
                $this->subscriptionManager->update($subscription);
                $detached[] = $subscription->id();

                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: Subscriber for "%s" not found and has been marked as detached.',
                        $subscription->id(),
                    ),
                );
            },
        );

        if ($detached === []) {
            return;
        }

        // every partition, also booting or failed ones, otherwise they would be retried without a subscriber.
        // a worker that is processing one is waited for, it would overwrite the status otherwise.
        $this->subscriptionManager->forEachClaimedGroup(
            new SubscriptionCriteria(
                ids: $detached,
                status: [
                    Status::New,
                    Status::Booting,
                    Status::Active,
                    Status::Paused,
                    Status::Finished,
                    Status::Error,
                    Status::Failed,
                ],
                partitions: true,
            ),
            new ClaimGroups(partitions: self::PARTITIONS_PER_CLAIM),
            function (array $partitions): void {
                foreach ($partitions as $partition) {
                    $partition->detached();
                    $this->subscriptionManager->update($partition);
                }
            },
            wait: true,
        );
    }
}
