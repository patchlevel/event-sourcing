<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events\ProfileCreated;

use function assert;

#[Projector('profile_change_name')]
final class ChangeNameProcessor
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
