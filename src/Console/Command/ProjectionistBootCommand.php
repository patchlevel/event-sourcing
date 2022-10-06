<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projectionist:boot',
    'Prepare new projections and catch up with the event store'
)]
final class ProjectionistBootCommand extends ProjectionistCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->projectorCriteria();
        $this->projectionist->boot($criteria);

        return 0;
    }
}
