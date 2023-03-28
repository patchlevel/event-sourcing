<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[Aggregate(ProfileWithSuppressAll::class)]
#[SuppressMissingApply(SuppressMissingApply::ALL)]
final class ProfileWithSuppressAll extends BasicAggregateRoot
{
    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $email));

        return $self;
    }

    public function aggregateRootId(): string
    {
        return '1';
    }
}
