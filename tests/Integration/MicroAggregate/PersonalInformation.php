<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\MicroAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Stream;
use Patchlevel\EventSourcing\Tests\Integration\MicroAggregate\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\MicroAggregate\Events\ProfileCreated;

#[Aggregate('personal_information')]
#[Stream(Profile::class)]
final class PersonalInformation extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    private string $name;

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
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

    public function changeName(string $name): void
    {
        $this->recordThat(new NameChanged($this->id, $name));
    }
}
