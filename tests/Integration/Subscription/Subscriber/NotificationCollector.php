<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NotificationSent;

#[Subscriber('notification', RunMode::FromBeginning)]
final class NotificationCollector
{
    /** @var list<NotificationSent> */
    public array $notifications = [];

    #[Subscribe(NotificationSent::class)]
    public function onNotificationSent(NotificationSent $event): void
    {
        $this->notifications[] = $event;
    }
}
