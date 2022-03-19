<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
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
        $self->record(new ProfileCreated($id));

        return $self;
    }

    public function visit(): void
    {
        $this->record(new OldVisited($this->id));
    }

    public function privacy(): void
    {
        $this->record(new PrivacyAdded($this->id));
    }

    public function isPrivate(): bool
    {
        return $this->privacy;
    }

    public function count(): int
    {
        return $this->visited;
    }

    #[Apply(ProfileCreated::class)]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->privacy = false;
        $this->visited = 0;
    }

    #[Apply(OldVisited::class)]
    protected function applyOldVisited(OldVisited $event): void
    {
        $this->visited++;
    }

    #[Apply(NewVisited::class)]
    protected function applyNewVisited(NewVisited $event): void
    {
        $this->visited--;
    }

    #[Apply(PrivacyAdded::class)]
    protected function applyPrivacyAdded(PrivacyAdded $event): void
    {
        $this->privacy = true;
    }
}
