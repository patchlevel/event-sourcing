<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projection:teardown',
    'Shut down and delete the outdated projections',
)]
final class ProjectionTeardownCommand extends ProjectionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->projectionCriteria($input);
        $this->projectionist->teardown($criteria);

        return 0;
    }
}
