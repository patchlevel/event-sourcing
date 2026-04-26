<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

use function sprintf;

/** @internal */
final class MessageProcessor
{
    public function __construct(
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly EventDispatcher $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function process(int $index, Message $message, Subscription $subscription): Error|null
    {
        $subscriber = $this->subscriberRepository->get($subscription->id());

        if (!$subscriber) {
            throw SubscriberNotFound::forSubscriptionId($subscription->id());
        }

        $subscribeMethods = $subscriber->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            if (!isset($this->batching[$subscription->id()])) {
                $subscription->changePosition($index);
            }

            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                ),
            );

            return null;
        }

        try {
            $event =  new OnHandleMessage(
                $subscription,
                $message,
            );

            $this->eventDispatcher->dispatch($event);
        } catch (Throwable $e) {
            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        try {
            foreach ($subscribeMethods as $subscribeMethod) {
                $subscribeMethod($message);
            }
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" could not process the event "%s": %s',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                    $e->getMessage(),
                ),
            );

            $this->eventDispatcher->dispatch(
                new OnHandleMessageError(
                    $subscription,
                    $e,
                    $message,
                    $index,
                ),
            );

            return new Error(
                $subscription->id(),
                $e->getMessage(),
                $e,
            );
        }

        $event = new OnHandleMessageSuccess(
            $subscription,
            $message,
            $index,
        );

        $this->eventDispatcher->dispatch($event);

        if ($event->shouldChangePosition) {
            $subscription->changePosition($index);
        }

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s".',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
            ),
        );

        return null;
    }
}
