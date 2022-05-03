<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Outbox\Processor;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\SendEmailMock;

final class SendEmailProcessor implements Listener
{
    public function __invoke(Message $message): void
    {
        if (!$message->event() instanceof ProfileCreated) {
            return;
        }

        SendEmailMock::send();
    }
}
