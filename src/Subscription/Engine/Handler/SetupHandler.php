<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriberNotFound;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Psr\Log\LoggerInterface;
use Throwable;

use function count;
use function sprintf;

/**
 * @internal
 *
 * @implements Handler<Setup>
 */
final class SetupHandler implements Handler
{
    public function __construct(
        private readonly MessageLoader $messageLoader,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        $this->logger?->info(
            'Subscription Engine: Start to setup.',
        );

        return $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::New],
            ),
            function (SubscriptionCollection $subscriptions) use ($command): Result {
                if (count($subscriptions) === 0) {
                    $this->logger?->info('Subscription Engine: No subscriptions to setup, finish setup.');

                    return new Result();
                }

                /** @var list<Error> $errors */
                $errors = [];

                $latestIndex = $this->messageLoader->lastIndex();

                foreach ($subscriptions as $subscription) {
                    $subscriber = $this->subscriberRepository->get($subscription->id());

                    if (!$subscriber) {
                        throw SubscriberNotFound::forSubscriptionId($subscription->id());
                    }

                    $setupMethod = $subscriber->setupMethod();

                    if (!$setupMethod) {
                        if ($subscription->runMode() === RunMode::FromNow) {
                            $subscription->changePosition($latestIndex);
                            $subscription->active();
                        } else {
                            $command->skipBooting ? $subscription->active() : $subscription->booting();
                        }

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has no setup method, set to %s.',
                            $subscriber::class,
                            $subscription->id(),
                            $subscription->runMode() === RunMode::FromNow || $command->skipBooting ? 'active' : 'booting',
                        ));

                        continue;
                    }

                    try {
                        $setupMethod();

                        if ($subscription->runMode() === RunMode::FromNow) {
                            $subscription->changePosition($latestIndex);
                            $subscription->active();
                        } else {
                            $command->skipBooting ? $subscription->active() : $subscription->booting();
                        }

                        $this->subscriptionManager->update($subscription);

                        $this->logger?->debug(sprintf(
                            'Subscription Engine: For Subscriber "%s" for "%s" the setup method has been executed, set to %s.',
                            $subscriber::class,
                            $subscription->id(),
                            $subscription->runMode() === RunMode::FromNow || $command->skipBooting ? 'active' : 'booting',
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has an error in the setup method: %s',
                            $subscriber::class,
                            $subscription->id(),
                            $e->getMessage(),
                        ));

                        $this->handleError($subscription, $e);

                        $errors[] = new Error(
                            $subscription->id(),
                            $e->getMessage(),
                            $e,
                        );
                    }
                }

                return new Result($errors);
            },
        );
    }
}
