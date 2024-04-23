<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\SendEmailMock;

#[Processor('send_email')]
final class SendEmailProcessor
{
    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        SendEmailMock::send();
    }
}
