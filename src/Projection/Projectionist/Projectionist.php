<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\Projection\Projection\Projection;

interface Projectionist
{
    /**
     * @param positive-int|null $limit
     *
     * @throws ProjectorNotFound
     */
    public function boot(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
    ): void;

    /**
     * @param positive-int|null $limit
     *
     * @throws ProjectorNotFound
     */
    public function run(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
    ): void;

    public function teardown(ProjectionistCriteria|null $criteria = null): void;

    public function remove(ProjectionistCriteria|null $criteria = null): void;

    public function reactivate(ProjectionistCriteria|null $criteria = null): void;

    public function pause(ProjectionistCriteria|null $criteria = null): void;

    /** @return list<Projection> */
    public function projections(ProjectionistCriteria|null $criteria = null): array;
}
