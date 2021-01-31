<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\NewVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\OldVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\PrivacyAdded;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\ProfileCreated;

final class Profile extends AggregateRoot
{
    private string $id;
    private bool $privacy;
    private int $visited;

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

    public function visit(): void
    {
        $this->apply(OldVisited::raise($this->id));
    }

    public function privacy(): void
    {
        $this->apply(PrivacyAdded::raise($this->id));
    }

    public function isPrivate(): bool
    {
        return $this->privacy;
    }

    public function count(): int
    {
        return $this->visited;
    }

    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId();
        $this->privacy = false;
        $this->visited = 0;
    }

    protected function applyOldVisited(OldVisited $event): void
    {
        $this->visited++;
    }

    protected function applyNewVisited(NewVisited $event): void
    {
        $this->visited--;
    }

    protected function applyPrivacyAdded(PrivacyAdded $event): void
    {
        $this->privacy = true;
    }
}
