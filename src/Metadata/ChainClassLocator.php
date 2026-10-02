<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

use function array_merge;
use function array_unique;
use function array_values;

final class ChainClassLocator implements ClassLocator
{
    /** @param iterable<ClassLocator> $locators */
    public function __construct(
        private readonly iterable $locators,
    ) {
    }

    /** @return list<class-string> */
    public function locate(): array
    {
        $classes = [];

        foreach ($this->locators as $locator) {
            $classes[] = $locator->locate();
        }

        return array_values(array_unique(array_merge(...$classes)));
    }
}
