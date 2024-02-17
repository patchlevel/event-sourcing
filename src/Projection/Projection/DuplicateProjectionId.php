<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use RuntimeException;

use function sprintf;

final class DuplicateProjectionId extends RuntimeException
{
    public function __construct(string $projectionId)
    {
        parent::__construct(sprintf('projection with the id "%s" exist already', $projectionId));
    }
}
