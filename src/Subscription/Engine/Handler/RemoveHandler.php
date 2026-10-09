<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
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
 * @implements Handler<Remove>
 */
final class RemoveHandler implements Handler
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
        $results = $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
            ),
            function (Subscription $subscription): Result {
                if ($subscription->isNew()) {
                    $this->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed.',
                            $subscription->id(),
                        ),
                    );

                    return new Result();
                }

                if ($subscription->hasCleanupTasks()) {
                    $error = $this->cleanupRunner->cleanup($subscription, true);

                    // the cleanup runner removes the subscription (forced, even on error)
                    $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($subscription));

                    return new Result($error ? [$error] : []);
                }

                $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

                if (!$subscriber) {
                    $this->remove($subscription);

                    $this->logger?->info(
                        sprintf(
                            'Subscription Engine: Subscription "%s" removed without a suitable subscriber.',
                            $subscription->id(),
                        ),
                    );

                    return new Result();
                }

                /** @var list<Error> $errors */
                $errors = [];

                $teardownMethod = $subscriber->teardownMethod();

                if ($teardownMethod) {
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

                        $errors[] = new Error($subscription->id(), $e->getMessage(), $e);
                    }
                }

                $this->remove($subscription);

                $this->logger?->info(
                    sprintf('Subscription Engine: Subscription "%s" removed.', $subscription->id()),
                );

                return new Result($errors);
            },
            static fn (Subscription $subscription, Throwable $e): Result => new Result(
                [new Error($subscription->id(), $e->getMessage(), $e)],
            ),
        );

        return Result::merge($results);
    }

    private function remove(Subscription $subscription): void
    {
        $this->subscriptionManager->remove($subscription);
        $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($subscription));
    }
}
