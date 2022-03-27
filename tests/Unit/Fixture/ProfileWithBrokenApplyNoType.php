<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;

final class ProfileWithBrokenApplyNoType extends AggregateRoot
{
    #[Apply]

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    protected function applyWithNoType($event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
