<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\ProfileId;

#[Processor('profile')]
final class ProfileProcessor
{
    public function __construct(
        private RepositoryManager $repositoryManager,
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $repository = $this->repositoryManager->get(Profile::class);

        $profile = $repository->load($profileCreated->profileId);

        $profile->changeName('admin');

        $repository->save($profile);
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $nameChanged, ProfileId $profileId): void
    {
        $repository = $this->repositoryManager->get(Profile::class);

        $profile = $repository->load($profileId);

        if ($profile->name() !== 'admin') {
            return;
        }

        $profile->promoteToAdmin();

        $repository->save($profile);
    }
}
