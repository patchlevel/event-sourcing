<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\ChildAggregate;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events\ProfileCreated;

#[ChildAggregate('personal_information')]
final class PersonalInformation extends BasicChildAggregate
{
    private string $name;

    public static function create(callable $recorder): self
    {
        return new self($recorder);
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->name = $event->name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
