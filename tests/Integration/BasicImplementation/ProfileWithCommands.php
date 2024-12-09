<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Inject;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\CreateProfile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Psr\Clock\ClockInterface;

#[Aggregate('profile_with_commands')]
#[Snapshot('default', 100)]
final class ProfileWithCommands extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private string $name;

    #[Handle]
    public static function create(
        CreateProfile $command,
        #[Inject]
        ClockInterface $clock,
    ): self {
        $self = new self();
        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    #[Handle]
    public function changeName(
        ChangeProfileName $command,
        #[Inject]
        ClockInterface $clock,
    ): void {
        $this->recordThat(new NameChanged($command->name));
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
