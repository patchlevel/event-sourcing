<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

/** @psalm-immutable */
final class ProjectionistCriteria
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
    ) {
    }
}
