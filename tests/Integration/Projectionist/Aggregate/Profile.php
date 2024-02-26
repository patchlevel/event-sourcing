<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\ProfileId;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer]
    private ProfileId $id;
    private string $name;

    public static function create(ProfileId $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));

        return $self;
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
