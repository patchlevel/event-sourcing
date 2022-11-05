<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use RuntimeException;

use function sprintf;

final class ProjectorNotFound extends RuntimeException
{
    public function __construct(ProjectorId $projectorId)
    {
        parent::__construct(sprintf('projector with the id "%s" not found', $projectorId->toString()));
    }
}
