<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\Projection\Projection\Projection;

interface Projectionist
{
    /**
     * @throws ProjectionistError
     * @throws ProjectorNotFound
     */
    public function boot(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
        bool $throwByError = false,
    ): void;

    /**
     * @param positive-int $limit
     *
     * @throws ProjectionistError
     * @throws ProjectorNotFound
     */
    public function run(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
        bool $throwByError = false,
    ): void;

    public function teardown(ProjectionistCriteria|null $criteria = null): void;

    public function remove(ProjectionistCriteria|null $criteria = null): void;

    public function reactivate(ProjectionistCriteria|null $criteria = null): void;

    /** @return iterable<Projection> */
    public function projections(ProjectionistCriteria|null $criteria = null): iterable;
}
