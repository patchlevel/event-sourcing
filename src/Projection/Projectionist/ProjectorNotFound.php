<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use RuntimeException;

use function sprintf;

final class ProjectorNotFound extends RuntimeException
{
    public function __construct(ProjectionId $projectorId)
    {
        parent::__construct(sprintf('projector with the projection id "%s" not found', $projectorId->toString()));
    }
}
