<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projectionist:reactivate',
    'Reactivate failed projections'
)]
final class ProjectionistReactivateCommand extends ProjectionistCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->projectorCriteria($input);
        $this->projectionist->reactivate($criteria);

        return 0;
    }
}
