<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;

#[SuppressMissingApply(SuppressMissingApply::ALL)]
final class ProfileWithAttributeApplySuppressAll extends AggregateRoot
{
    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $email));

        return $self;
    }

    public function aggregateRootId(): string
    {
        return '1';
    }
}
