<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use RuntimeException;

#[Subscriber('error_producer')]
final class ErrorProducerSubscriber
{
    public bool $setupError = false;
    public bool $subscribeError = false;
    public bool $teardownError = false;

    #[Setup]
    public function setup(): void
    {
        if ($this->setupError) {
            throw new RuntimeException('setup error');
        }
    }

    #[Teardown]
    public function teardown(): void
    {
        if ($this->teardownError) {
            throw new RuntimeException('teardown error');
        }
    }

    #[Subscribe('*')]
    public function subscribe(Message $message): void
    {
        if ($this->subscribeError) {
            throw new RuntimeException('subscribe error');
        }
    }
}
