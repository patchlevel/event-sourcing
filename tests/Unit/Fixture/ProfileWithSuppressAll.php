<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[SuppressMissingApply(SuppressMissingApply::ALL)]
final class ProfileWithSuppressAll extends AggregateRoot
{
    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(new ProfileCreated($id, $email));

        return $self;
    }

    public function aggregateRootId(): string
    {
        return '1';
    }
}
