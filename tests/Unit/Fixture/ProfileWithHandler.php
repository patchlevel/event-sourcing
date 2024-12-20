<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate(ProfileWithHandler::class)]
final class ProfileWithHandler extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Handle]
    public static function create(CreateProfile $command): self
    {
        return new self();
    }

    public static function createEmpty(): self
    {
        return new self();
    }

    #[Handle]
    public function changeName(ChangeProfileName $command): void
    {
    }
}
