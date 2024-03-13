<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

use function assert;

#[Processor('profile')]
final class ProfileProcessor
{
    public function __construct(
        private RepositoryManager $repositoryManager,
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();

        assert($profileCreated instanceof ProfileCreated);

        $repository = $this->repositoryManager->get(Profile::class);

        $profile = $repository->load($profileCreated->profileId);

        $profile->changeName('new name');

        $repository->save($profile);
    }
}
