<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
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
 * @implements Handler<null>
 */
final class TeardownHandler implements Handler
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        $this->logger?->info('Subscription Engine: Start teardown detached subscriptions.');

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
                        $error = $this->cleanup($subscription);

                        if ($error) {
                            $errors[] = $error;
                        }

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
                        $this->subscriptionManager->remove($subscription);

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

                    $this->subscriptionManager->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed.',
                            $subscription->id(),
                        ),
                    );
                }

                $this->logger?->info('Subscription Engine: Finish teardown.');

                return new Result($errors);
            },
        );
    }
}
