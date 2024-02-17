<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use RuntimeException;

use function sprintf;

final class ProjectorNotFound extends RuntimeException
{
    public static function forProjectionId(string $projectionId): self
    {
        return new self(
            sprintf(
                'projector with the projection id "%s" not found',
                $projectionId,
            ),
        );
    }
}
