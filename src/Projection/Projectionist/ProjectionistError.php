<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use RuntimeException;
use Throwable;

use function sprintf;

final class ProjectionistError extends RuntimeException
{
    public function __construct(
        public readonly string $projector,
        public readonly ProjectionId $projectionId,
        Throwable $error,
    ) {
        parent::__construct(
            sprintf(
                'error in projector "%s" for "%s": %s',
                $projector,
                $projectionId->toString(),
                $error->getMessage(),
            ),
            0,
            $error,
        );
    }
}
