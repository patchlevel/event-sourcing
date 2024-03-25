<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Listener;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\SendEmailMock;

final class SendEmailListener
{
    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        SendEmailMock::send();
    }
}
