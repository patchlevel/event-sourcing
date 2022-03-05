<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProjectionDropCommand extends ProjectionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:projection:drop')
            ->setDescription('drop projection schema')
            ->addOption('projection', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'run only for specific projections [FQCN]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $projections = $this->projections($input->getOption('projection'));

        $this->projectionRepository->drop($projections);

        $console->success('projection deleted');

        return 0;
    }
}
