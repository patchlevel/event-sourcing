<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithEmptyApply::class)]
final class ProfileWithEmptyApply extends AggregateRoot
{
    #[Apply]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
