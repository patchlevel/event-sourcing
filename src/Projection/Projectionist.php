<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;

interface Projectionist
{
    public function boot(ProjectorCriteria $criteria = new ProjectorCriteria(), ?int $limit = null): void;

    /**
     * @param positive-int $limit
     */
    public function run(ProjectorCriteria $criteria = new ProjectorCriteria(), ?int $limit = null): void;

    public function teardown(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    public function remove(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    public function reactivate(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    public function projectorStates(): ProjectorStateCollection;
}
