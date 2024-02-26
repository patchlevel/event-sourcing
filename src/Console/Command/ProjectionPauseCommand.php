<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projection:pause',
    'Set projection to pause',
)]
final class ProjectionPauseCommand extends ProjectionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->projectionCriteria($input);
        $this->projectionist->pause($criteria);

        return 0;
    }
}
