<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Projection\Projectionist;
use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Symfony\Component\Console\Command\Command;

abstract class ProjectionistCommand extends Command
{
    public function __construct(
        protected readonly Projectionist $projectionist
    ) {
        parent::__construct();
    }

    protected function projectorCriteria(): ProjectorCriteria
    {
        return new ProjectorCriteria();
    }
}
