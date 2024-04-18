<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use RuntimeException;

use function sprintf;

final class UnsupportedCriterion extends RuntimeException
{
    /** @param class-string $criterionClass */
    public function __construct(string $criterionClass)
    {
        parent::__construct(sprintf('criterion %s not supported', $criterionClass));
    }
}
