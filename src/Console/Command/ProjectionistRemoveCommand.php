<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projectionist:remove',
    'Delete a projection and remove it from the store',
)]
final class ProjectionistRemoveCommand extends ProjectionistCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->projectionCriteria($input);

        if ($criteria->ids === null) {
            if (!$io->confirm('do you want to remove all projections?', false)) {
                return 1;
            }
        }

        $this->projectionist->remove($criteria);

        return 0;
    }
}
