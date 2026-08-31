<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the MessageContext in sync with the message the engine is currently handling,
 * so that messages recorded by a subscriber inherit correlation and causation ids.
 *
 * @internal
 */
final class MessageContextSubscriber implements EventSubscriberInterface
{
    private bool $pushed = false;

    public function __construct(
        private readonly MessageContext $messageContext,
    ) {
    }

    /**
     * Only ever removes the frame this listener pushed itself.
     *
     * The context is shared with the command bus and the event bus, and an engine run can
     * happen inside one of their scopes, so clearing the whole stack here would destroy
     * frames which belong to the caller.
     */
    public function onCommand(OnCommand $event): void
    {
        $this->popIfPushed();
    }

    public function onHandleMessage(OnHandleMessage $event): void
    {
        $this->messageContext->pushMessage($event->message);
        $this->pushed = true;
    }

    public function onHandleMessageSuccess(OnHandleMessageSuccess $event): void
    {
        $this->popIfPushed();
    }

    public function onHandleMessageError(OnHandleMessageError $event): void
    {
        $this->popIfPushed();
    }

    /**
     * A subscriber without a matching subscribe method is reported as success
     * without a preceding OnHandleMessage, so we must not pop in that case.
     * The same guard keeps a second terminal event from popping twice.
     */
    private function popIfPushed(): void
    {
        if (!$this->pushed) {
            return;
        }

        $this->messageContext->pop();
        $this->pushed = false;
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        // the frame is removed before any other listener reacts to the terminal event,
        // so writes which are not attributable to this message, like a batch flush,
        // do not inherit its ids
        return [
            OnCommand::class => ['onCommand', 64],
            OnHandleMessage::class => ['onHandleMessage', 64],
            OnHandleMessageSuccess::class => ['onHandleMessageSuccess', 64],
            OnHandleMessageError::class => ['onHandleMessageError', 64],
        ];
    }
}
