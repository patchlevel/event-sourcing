<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use RuntimeException;

use function sprintf;

final class ProjectorInformationNotFound extends RuntimeException
{
    public function __construct(ProjectorId $projectorId)
    {
        parent::__construct(sprintf('%s', $projectorId->toString()));
    }
}
