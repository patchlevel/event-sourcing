<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\MessageDecorator;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;

final class FooMessageDecorator implements MessageDecorator
{
    public function __invoke(Message $message): Message
    {
        return $message->withHeader('foo', 'bar');
    }
}
