<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

final class ProjectorCriteria
{
    /**
     * @param list<string>|null $names
     */
    public function __construct(
        public readonly ?array $names = null
    ) {
    }
}
