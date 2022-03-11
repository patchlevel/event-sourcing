<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProjectionCreateCommand extends ProjectionCommand
{
    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:projection:create')
            ->setDescription('create projection schema')
            ->addOption('projection', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'run only for specific projections [FQCN]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $this->projectionHandler($input->getOption('projection'))->create();

        $console->success('projection created');

        return 0;
    }
}
