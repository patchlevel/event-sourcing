<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container\MessageDecorator;

use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Message;

final class FooMessageDecorator implements MessageDecorator
{
    public function __invoke(Message $message): Message
    {
        return $message->withCustomHeader('foo', 'bar');
    }
}
