<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate(ProfileWithHandlers::class)]
final class ProfileWithHandlers extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Handle]
    public static function create(CreateProfile $command): void
    {
    }

    #[Handle]
    public function changeName(ChangeProfileName $command): void
    {
    }

    #[Handle(NoParameterCommand::class)]
    public function noParameters(): void
    {
    }

    #[Handle(NoTypeCommand::class)]
    public function noType($command): void
    {
    }
}
