<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Teardown>
 */
final class TeardownHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly CleanupRunner $cleanupRunner,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Detached],
            ),
            function (SubscriptionCollection $subscriptions): Result {
                /** @var list<Error> $errors */
                $errors = [];

                foreach ($subscriptions as $subscription) {
                    if ($subscription->hasCleanupTasks()) {
                        $error = $this->cleanupRunner->cleanup($subscription);

                        if ($error) {
                            $errors[] = $error;

                            continue;
                        }

                        // the cleanup runner removed the subscription
                        $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($subscription));

                        continue;
                    }

                    $subscriber = $this->subscriberRepository->get($subscription->id());

                    if (!$subscriber) {
                        $this->logger?->warning(
                            sprintf(
                                'Subscription Engine: Subscriber for "%s" to teardown or cleanup not found, skipped.',
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $subscriber->teardownMethod();

                    if (!$teardownMethod) {
                        $this->remove($subscription);

                        $this->logger?->info(
                            sprintf(
                                'Subscription Engine: Subscriber "%s" for "%s" has no teardown method and was immediately removed.',
                                $subscriber::class,
                                $subscription->id(),
                            ),
                        );

                        continue;
                    }

                    try {
                        $teardownMethod();

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: For Subscriber "%s" for "%s" the teardown method has been executed and is now prepared to be removed.',
                            $subscriber::class,
                            $subscription->id(),
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            sprintf(
                                'Subscription Engine: Subscription "%s" for "%s" has an error in the teardown method, skipped: %s',
                                $subscriber::class,
                                $subscription->id(),
                                $e->getMessage(),
                            ),
                        );

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );

                        continue;
                    }

                    $this->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed.',
                            $subscription->id(),
                        ),
                    );
                }

                return new Result($errors);
            },
        );
    }

    private function remove(Subscription $subscription): void
    {
        $this->subscriptionManager->remove($subscription);
        $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($subscription));
    }
}
