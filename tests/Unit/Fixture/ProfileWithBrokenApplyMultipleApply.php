<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyMultipleApply::class)]
final class ProfileWithBrokenApplyMultipleApply extends AggregateRoot
{
    #[Apply]
    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
