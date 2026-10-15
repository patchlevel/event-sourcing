<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\FailedMessageProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** @internal */
final class FailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FailedMessageProcessor $failedMessageProcessor,
    ) {
    }

    public function onHandleMessageError(OnHandleMessageError $event): void
    {
        if (!$event->transitionToFailed) {
            return;
        }

        $this->failedMessageProcessor->process($event->subscription, $event->throwable, $event->message, $event->index);
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            OnHandleMessageError::class => ['onHandleMessageError', -8],
        ];
    }
}
