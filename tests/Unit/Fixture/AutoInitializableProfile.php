<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\AutoInitialize;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('auto_initializable_profile')]
final class AutoInitializableProfile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    public function id(): ProfileId
    {
        return $this->id;
    }

    #[AutoInitialize]
    public static function initialize(ProfileId $id): static
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, Email::fromString('initial@patchlevel.de')));

        return $self;
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
    }
}
