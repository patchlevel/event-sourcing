<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use RuntimeException;

use function sprintf;

final class CriterionNotFound extends RuntimeException
{
    /** @param class-string $criterionClass */
    public function __construct(string $criterionClass)
    {
        parent::__construct(sprintf('criterion %s not found', $criterionClass));
    }
}
