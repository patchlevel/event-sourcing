<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/**
 * Processes the message a subscription finally failed on, after its retries are used up: the #[OnFailed] method of the
 * subscriber gets the message and the subscription moves on, without such a method the subscription fails.
 *
 * @internal
 */
final class FailedMessageProcessor
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function process(
        Subscription $subscription,
        Throwable $throwable,
        Message|null $message = null,
        int|null $index = null,
    ): void {
        if (!$message || $index === null) {
            $subscription->failed($throwable);
            $this->subscriptionManager->update($subscription);

            return;
        }

        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            $subscription->failed($throwable);
            $this->subscriptionManager->update($subscription);

            return;
        }

        if ($subscriber->metadata()->batch !== null) {
            $subscription->failed($throwable);
            $this->subscriptionManager->update($subscription);

            return;
        }

        $failedMethod = $subscriber->failedMethod();

        if (!$failedMethod) {
            $subscription->failed($throwable);
            $this->subscriptionManager->update($subscription);

            return;
        }

        try {
            $failedMethod($message, $throwable);
            $subscription->changePosition($index);
            $subscription->resetRetry();

            $this->subscriptionManager->update($subscription);
        } catch (Throwable $e) {
            $this->logger?->error(sprintf(
                'Subscription Engine: Subscriber "%s" has an error in the failed method: %s',
                $subscription->id(),
                $e->getMessage(),
            ));

            $subscription->failed($throwable);
            $this->subscriptionManager->update($subscription);
        }
    }
}
