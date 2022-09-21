<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Psr\Log\LoggerInterface;

interface Projectionist
{
    public function boot(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void;

    public function run(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?int $limit = null,
        ?LoggerInterface $logger = null
    ): void;

    public function teardown(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void;

    public function remove(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void;

    public function status(): ProjectorStateCollection;
}
