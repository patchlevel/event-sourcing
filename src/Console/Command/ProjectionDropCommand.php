<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProjectionDropCommand extends Command
{
    private ProjectionRepository $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        parent::__construct();

        $this->projectionRepository = $projectionRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:projection:drop')
            ->setDescription('drop projection schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $this->projectionRepository->drop();

        $console->success('projection deleted');

        return 0;
    }
}
