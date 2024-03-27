<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\MessageDecorator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Header\FooHeader;

final class FooMessageDecorator implements MessageDecorator
{
    public function __invoke(Message $message): Message
    {
        return $message->withHeader(new FooHeader('bar'))->withHeader(new BazHeader('test'));
    }
}
