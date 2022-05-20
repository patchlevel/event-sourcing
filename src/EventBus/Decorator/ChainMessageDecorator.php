<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Decorator;

use Patchlevel\EventSourcing\EventBus\Message;

final class ChainMessageDecorator implements MessageDecorator
{
    /**
     * @param array<MessageDecorator> $messageDecorators
     */
    public function __construct(private array $messageDecorators)
    {
    }

    public function __invoke(Message $message): Message
    {
        foreach ($this->messageDecorators as $messageDecorator) {
            $message = ($messageDecorator)($message);
        }

        return $message;
    }
}