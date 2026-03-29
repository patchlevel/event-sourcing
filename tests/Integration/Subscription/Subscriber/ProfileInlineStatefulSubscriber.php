<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Subscription\StatefulSubscriber\StatefulSubscriber;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\ProfileId;

#[Projector('profile_inline')]
final class ProfileInlineStatefulSubscriber extends StatefulSubscriber
{
    /** @var array<string, string> */
    public array $profiles = [];

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->profiles[$profileCreated->profileId->toString()] = $profileCreated->name;
    }

    public function findById(ProfileId $id): string|null
    {
        return $this->profiles[$id->toString()] ?? null;
    }
}
