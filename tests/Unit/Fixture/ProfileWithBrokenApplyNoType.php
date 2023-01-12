<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyNoType::class)]
final class ProfileWithBrokenApplyNoType extends AggregateRoot
{
    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    #[Apply]
    protected function applyWithNoType($event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
