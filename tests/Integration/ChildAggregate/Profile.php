<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\ProfileCreated;

#[Aggregate('profile')]
#[Snapshot('default', 100)]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private PersonalInformation $personalInformation;

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
        $this->personalInformation = PersonalInformation::create();
    }

    public function name(): string
    {
        return $this->personalInformation->name();
    }

    /**
     * This could maybe be solved by also marking them via attribute as child aggregates
     *
     * @return array<ChildAggregate>
     */
    public function getChildren(): array
    {
        return [
            $this->personalInformation,
        ];
    }
}
