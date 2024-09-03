<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\NameChanged;

final class PersonalInformation extends BasicChildAggregate
{
    public function __construct(
        private string $name,
    ) {
    }

    #[Apply(NameChanged::class)]
    public function applyNameChanged(NameChanged $event): void
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
