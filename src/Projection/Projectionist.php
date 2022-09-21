<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;

interface Projectionist
{
    public function boot(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    public function run(ProjectorCriteria $criteria = new ProjectorCriteria(), ?int $limit = null): void;

    public function teardown(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    public function remove(ProjectorCriteria $criteria = new ProjectorCriteria()): void;

    /**
     * @return list<ProjectorState>
     */
    public function status(): array;
}
