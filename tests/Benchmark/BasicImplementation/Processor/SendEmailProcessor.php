<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Processor;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\SendEmailMock;

final class SendEmailProcessor extends Subscriber
{
    #[Handle(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        SendEmailMock::send();
    }
}
