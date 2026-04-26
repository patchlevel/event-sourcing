<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
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
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        $subscriptions = $this->subscriptionManager->find(new SubscriptionCriteria(
            ids: $command->ids,
            groups: $command->groups,
        ));

        foreach ($subscriptions as $subscription) {
            $subscriber = $this->subscriberRepository->get($subscription->id());

            if (!$subscriber) {
                continue;
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

            $cleanupTasks = $this->cleanupTasks($subscriber);

            if ($subscription->cleanupTasks() !== $cleanupTasks) {
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
                continue;
            }

            $this->subscriptionManager->update($subscription);
        }

        $this->subscriptionManager->flush();

        return new Result();
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
