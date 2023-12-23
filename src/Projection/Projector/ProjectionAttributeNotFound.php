<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use RuntimeException;

use function sprintf;

final class ProjectionAttributeNotFound extends RuntimeException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(
            sprintf('no projection attribute found on class %s', $class),
        );
    }
}
