<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use RuntimeException;

use function sprintf;

final class ProjectionNotFound extends RuntimeException
{
    public function __construct(string $projectionId)
    {
        parent::__construct(sprintf('projection with the id "%s" not found', $projectionId));
    }
}
