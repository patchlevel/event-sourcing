<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;

interface Projectionist
{
    public function boot(ProjectionCriteria $criteria = new ProjectionCriteria(), int|null $limit = null): void;

    /** @param positive-int $limit */
    public function run(ProjectionCriteria $criteria = new ProjectionCriteria(), int|null $limit = null): void;

    public function teardown(ProjectionCriteria $criteria = new ProjectionCriteria()): void;

    public function remove(ProjectionCriteria $criteria = new ProjectionCriteria()): void;

    public function reactivate(ProjectionCriteria $criteria = new ProjectionCriteria()): void;

    public function projections(): ProjectionCollection;
}
