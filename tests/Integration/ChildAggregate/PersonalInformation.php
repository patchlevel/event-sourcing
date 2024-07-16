<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Integration\ChildAggregate\Events\NameChanged;
use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\ChildAggregate;

#[ChildAggregate('personal_information')]
final class PersonalInformation extends BasicChildAggregate
{
    private string $name;

    public static function create(string $name): self
    {
        $personalInformation = new self();
        $personalInformation->name = $name;

        return $personalInformation;
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
        $this->recordThat(new NameChanged($name));
    }
}
