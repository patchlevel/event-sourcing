<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use RuntimeException;

use function sprintf;

final class ProjectionAlreadyExists extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Projection "%s" already exists', $id));
    }
}
