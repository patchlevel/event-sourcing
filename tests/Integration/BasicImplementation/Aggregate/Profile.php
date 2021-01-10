<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class Profile extends AggregateRoot
{
    private string $id;

    public function aggregateRootId(): string
    {
        return $this->id;
    }

    public static function create(string $id): self
    {
        $self = new self();
        $self->apply(ProfileCreated::raise($id));

        return $self;
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
    }
}
