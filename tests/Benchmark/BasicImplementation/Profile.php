<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\EmailChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\Reborn;

#[Aggregate('profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private string $name;
    private string|null $email;

    public static function create(ProfileId $id, string $name, string|null $email = null): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name, $email));

        return $self;
    }

    public function changeName(string $name): void
    {
        $this->recordThat(new NameChanged($name));
    }

    public function changeEmail(string $email): void
    {
        $this->recordThat(new EmailChanged($this->id, $email));
    }

    public function reborn(): void
    {
        $this->recordThat(new Reborn(
            $this->id,
            $this->name,
        ));
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
        $this->email = $event->email;
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyEmailChanged(EmailChanged $event): void
    {
        $this->email = $event->email;
    }

    #[Apply]
    protected function applyReborn(Reborn $event): void
    {
        $this->id = $event->profileId;
        $this->name = $event->name;
        $this->email = null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string|null
    {
        return $this->email;
    }
}
