<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber(ExternalSubscriber::ID, RunMode::FromBeginning)]
final class ExternalSubscriber
{
    public const ID = 'external_subscriber';

    /** @var list<Message> */
    public array $receivedMessages = [];

    #[Subscribe('*')]
    public function onMessage(Message $message): void
    {
        $this->receivedMessages[] = $message;
    }
}
