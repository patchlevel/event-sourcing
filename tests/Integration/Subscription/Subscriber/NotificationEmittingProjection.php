<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\EventEmitter;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NotificationSent;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

#[Subscriber('emitting', RunMode::FromBeginning)]
final class NotificationEmittingProjection
{
    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event, EventEmitter $eventEmitter): void
    {
        $eventEmitter->emit([new NotificationSent($event->profileId)]);
    }
}
