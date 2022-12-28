<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container\Processor;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;
use Patchlevel\EventSourcing\Tests\Integration\Container\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Container\SendEmailMock;

final class SendEmailProcessor extends Subscriber
{
    #[Handle(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        SendEmailMock::send();
    }
}
