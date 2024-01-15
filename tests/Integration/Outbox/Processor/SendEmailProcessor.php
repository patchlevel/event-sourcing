<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Outbox\Processor;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\SendEmailMock;

final class SendEmailProcessor
{
    #[Subscribe(ProfileCreated::class)]
    public function __invoke(Message $message): void
    {
        SendEmailMock::send();
    }
}
