<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectionCreateCommand extends Command
{
    private ProjectionRepository $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        parent::__construct();

        $this->projectionRepository = $projectionRepository;
    }

    protected function configure(): void
    {
        $this->setName('event-sourcing:projection:create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->projectionRepository->create();

        return 0;
    }
}