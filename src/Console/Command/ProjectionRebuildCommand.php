<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projection:rebuild',
    'Rebuild projections (remove & boot)',
)]
final class ProjectionRebuildCommand extends ProjectionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->projectionCriteria($input);

        if (!$io->confirm('do you want to rebuild all projections?', false)) {
            return 1;
        }

        $this->projectionist->remove($criteria);
        $this->projectionist->boot($criteria, null);

        return 0;
    }
}
