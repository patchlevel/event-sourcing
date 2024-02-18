<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

/** @psalm-immutable */
final class ProjectionCriteria
{
    /**
     * @param list<string>|null     $ids
     * @param list<string>|null     $groups
     * @param ProjectionStatus|null $status
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
        public readonly array|null $status = null,
    ) {
    }
}
