<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

/**
 * @psalm-immutable
 */
final class ProjectionCriteria
{
    /**
     * @param list<ProjectionId>|null $ids
     */
    public function __construct(
        public readonly ?array $ids = null,
    ) {
    }
}
