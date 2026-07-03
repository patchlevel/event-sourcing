<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriberNotFound;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ConditionalRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

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
        private readonly RetryStrategyRepository $retryStrategyRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Command $command): Result
    {
        $latestIndex = null;

        $results = $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::New],
            ),
            function (Subscription $subscription) use ($command, &$latestIndex): Result {
                $latestIndex ??= $this->messageLoader->lastIndex();

                $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

                if (!$subscriber) {
                    throw SubscriberNotFound::forSubscriptionId($subscription->id());
                }

                $setupMethod = $subscriber->setupMethod();

                if ($setupMethod) {
                    try {
                        $setupMethod();
                    } catch (Throwable $e) {
                        $this->logger?->error(sprintf(
                            'Subscription Engine: Subscriber "%s" for "%s" has an error in the setup method: %s',
                            $subscriber::class,
                            $subscription->id(),
                            $e->getMessage(),
                        ));

                        $this->handleError($subscription, $e);

                        return new Result([new Error($subscription->id(), $e->getMessage(), $e)]);
                    }
                }

                if ($subscription->runMode() === RunMode::FromNow) {
                    $subscription->changePosition($latestIndex);
                    $subscription->active();
                } else {
                    $command->skipBooting ? $subscription->active() : $subscription->booting();
                }

                $this->subscriptionManager->update($subscription);

                $this->logger?->debug(sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has been set up, set to %s.',
                    $subscriber::class,
                    $subscription->id(),
                    $subscription->runMode() === RunMode::FromNow || $command->skipBooting ? 'active' : 'booting',
                ));

                return new Result();
            },
            static fn (Subscription $subscription, Throwable $e): Result => new Result(
                [new Error($subscription->id(), $e->getMessage(), $e)],
            ),
        );

        return Result::merge($results);
    }

    private function handleError(Subscription $subscription, Throwable $throwable): void
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());
        $retryStrategy = $subscriber instanceof MetadataSubscriberAccessor && $subscriber->metadata()->retryStrategy !== null
            ? $this->retryStrategyRepository->get($subscriber->metadata()->retryStrategy)
            : $this->retryStrategyRepository->getDefaultRetryStrategy();

        if (!$retryStrategy instanceof ConditionalRetryStrategy || $retryStrategy->canRetry($subscription)) {
            $subscription->error($throwable);
        } else {
            $subscription->failed($throwable);
        }

        $this->subscriptionManager->update($subscription);
    }
}
