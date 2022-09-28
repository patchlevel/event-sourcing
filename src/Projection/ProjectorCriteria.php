<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

final class ProjectorCriteria
{
    /**
     * @param list<ProjectorId>|null $ids
     */
    public function __construct(
        public readonly ?array $ids = null,
    ) {
    }
}
