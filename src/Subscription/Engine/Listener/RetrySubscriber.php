<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionCollection;
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

        $this->subscriptionManager->findForUpdate(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Error],
            ),
            function (SubscriptionCollection $subscriptions) use ($status): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $error = $subscription->subscriptionError();

                    if ($error === null) {
                        continue;
                    }

                    if ($error->previousStatus !== $status) {
                        continue;
                    }

                    if (!$this->retryStrategy($subscription)->shouldRetry($subscription)) {
                        continue;
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
                }
            },
        );
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
        $event->subscription->resetRetry();
    }

    private function retryStrategy(Subscription $subscription): RetryStrategy
    {
        $subscriber = $this->subscriberRepository->get($subscription->id());

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
