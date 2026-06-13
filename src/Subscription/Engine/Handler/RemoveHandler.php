<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Remove>
 */
final class RemoveHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly CleanupRunner $cleanupRunner,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
            ),
            function (SubscriptionCollection $subscriptions): Result {
                /** @var list<Error> $errors */
                $errors = [];

                foreach ($subscriptions as $subscription) {
                    if ($subscription->isNew()) {
                        $this->subscriptionManager->remove($subscription);

                        $this->logger?->info(
                            sprintf(
                                'Subscription Engine: Subscription "%s" removed.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    if ($subscription->hasCleanupTasks()) {
                        $error = $this->cleanupRunner->cleanup($subscription, true);

                        if ($error) {
                            $errors[] = $error;
                        }

                        continue;
                    }

                    $subscriber = $this->subscriberRepository->get($subscription->id());

                    if (!$subscriber) {
                        $this->subscriptionManager->remove($subscription);

                        $this->logger?->info(
                            sprintf(
                                'Subscription Engine: Subscription "%s" removed without a suitable subscriber.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $subscriber->teardownMethod();

                    if (!$teardownMethod) {
                        $this->subscriptionManager->remove($subscription);

                        $this->logger?->info(
                            sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                        );

                        continue;
                    }

                    try {
                        $teardownMethod();
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            sprintf(
                                'Subscription Engine: Subscriber "%s" teardown method could not be executed: %s',
                                $subscriber::class,
                                $e->getMessage(),
                            ),
                        );

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );
                    }

                    $this->subscriptionManager->remove($subscription);

                    $this->logger?->info(
                        sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                    );
                }

                return new Result($errors);
            },
        );
    }
}
