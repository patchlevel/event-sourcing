<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile-inheritance')]
final class ProfileWithInheritanceHandler extends BasicAggregateRoot
{
    #[Id]
    private string $id = '1';

    #[Handle]
    public function handleInterface(SomeCommand $command): void
    {
    }

    #[Handle]
    public function handleAbstract(BaseCommand $command): void
    {
    }

    public function aggregateRootId(): AggregateRootId
    {
        return ProfileId::fromString($this->id);
    }
}
