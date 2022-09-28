<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use RuntimeException;

use function sprintf;

final class DuplicateProjectorId extends RuntimeException
{
    public function __construct(ProjectorId $projectorId)
    {
        parent::__construct(sprintf('projector id "%s" exist already', $projectorId->toString()));
    }
}
