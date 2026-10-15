<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ConditionalRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function sprintf;

/** @internal */
final class RetrySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly RetryStrategyRepository $retryStrategyRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onCommand(OnCommand $event): void
    {
        $command = $event->command;

        $status = match ($command::class) {
            Setup::class => Status::New,
            Boot::class => Status::Booting,
            Run::class => Status::Active,
            default => null,
        };

        if ($status === null) {
            return;
        }

        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Error],
            ),
            function (Subscription $subscription) use ($status): void {
                // checked again, another worker may have retried it since the snapshot
                if (!$this->isDue($subscription, $status)) {
                    return;
                }

                $subscription->doRetry();
                $this->subscriptionManager->update($subscription);

                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: Retry subscription "%s" (%d) and set back to %s.',
                        $subscription->id(),
                        $subscription->retryAttempt(),
                        $subscription->status()->value,
                    ),
                );
            },
            // only subscriptions whose retry is due are claimed, with many errored partitions most of them wait
            filter: fn (Subscription $subscription): bool => $this->isDue($subscription, $status),
        );
    }

    private function isDue(Subscription $subscription, Status $status): bool
    {
        $error = $subscription->subscriptionError();

        if ($error === null || $error->previousStatus !== $status) {
            return false;
        }

        return $this->retryStrategy($subscription)->shouldRetry($subscription);
    }

    public function onHandleMessageError(OnHandleMessageError $event): void
    {
        $retryStrategy = $this->retryStrategy($event->subscription);

        if (!$retryStrategy instanceof ConditionalRetryStrategy || $retryStrategy->canRetry($event->subscription)) {
            $event->subscription->error($event->throwable);
            $this->subscriptionManager->update($event->subscription);

            return;
        }

        $event->transitionToFailed = true;
    }

    public function onSuccessHandleMessage(OnHandleMessageSuccess $event): void
    {
        // the main subscription of a partitioned subscriber only routes, that works even if the partitions fail.
        // its retries are reset by the circuit breaker once its partitions succeed again.
        if (!$event->subscription->isPartition() && $this->subscriberRepository->get($event->subscription->subscriberId())?->metadata()->partition !== null) {
            return;
        }

        $event->subscription->resetRetry();
    }

    private function retryStrategy(Subscription $subscription): RetryStrategy
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber instanceof MetadataSubscriberAccessor) {
            return $this->retryStrategyRepository->getDefaultRetryStrategy();
        }

        $retryStrategy = $subscriber->metadata()->retryStrategy;

        if ($retryStrategy === null) {
            return $this->retryStrategyRepository->getDefaultRetryStrategy();
        }

        return $this->retryStrategyRepository->get($retryStrategy);
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            OnCommand::class => ['onCommand', 16],
            OnHandleMessageError::class => 'onHandleMessageError',
            OnHandleMessageSuccess::class => 'onSuccessHandleMessage',
        ];
    }
}
