<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Metadata\ClassLocator;

final class SplitStreamHeaderLocator implements ClassLocator
{
    /** @return list<class-string> */
    public function locate(): array
    {
        return [StreamStartHeader::class];
    }
}
