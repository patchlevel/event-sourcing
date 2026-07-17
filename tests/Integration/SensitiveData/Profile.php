<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\SensitiveData;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events\PersonalDataRemoved;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events\ProfileCreated;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Aggregate('profile')]
#[Snapshot('default', 2)]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[DataSubjectId]
    private ProfileId $id;

    #[SensitiveData(fallback: 'unknown')]
    private string $name;

    public static function create(ProfileId $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));

        return $self;
    }

    public function removePersonalData(): void
    {
        $this->recordThat(new PersonalDataRemoved($this->id));
    }

    public function changeName(string $name): void
    {
        $this->recordThat(new NameChanged($this->id, $name));
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
    }

    #[Apply(PersonalDataRemoved::class)]
    protected function applyPersonalDataRemoved(): void
    {
        $this->name = 'unknown';
    }

    #[Apply(NameChanged::class)]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
