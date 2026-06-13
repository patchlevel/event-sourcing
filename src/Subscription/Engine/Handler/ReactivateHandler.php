<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Reactivate>
 */
final class ReactivateHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        return $this->subscriptionManager->findForUpdate(
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
            function (SubscriptionCollection $subscriptions): Result {
                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriberRepository->get($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->debug(
                            sprintf(
                                'Subscription Engine: Subscriber for "%s" not found, skipped.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $error = $subscription->subscriptionError();

                    if ($error) {
                        $subscription->doRetry();
                        $subscription->resetRetry();

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->info(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                            $subscriber::class,
                            $subscription->id(),
                        ));

                        continue;
                    }

                    $subscription->active();
                    $this->subscriptionManager->update($subscription);

                    $this->logger?->info(sprintf(
                        'Subscription Engine: Subscriber "%s" for "%s" is reactivated.',
                        $subscriber::class,
                        $subscription->id(),
                    ));
                }

                return new Result();
            },
        );
    }
}
