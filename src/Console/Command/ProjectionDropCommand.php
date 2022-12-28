<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projection:drop',
    'drop projection schema'
)]
final class ProjectionDropCommand extends ProjectionCommand
{
    private readonly ProjectorResolver $projectorResolver;

    public function __construct(
        ProjectorRepository $projectorRepository,
        ProjectorResolver $projectorResolver = new MetadataProjectorResolver()
    ) {
        parent::__construct($projectorRepository);

        $this->projectorResolver = $projectorResolver;
    }

    protected function configure(): void
    {
        $this
            ->addOption('projection', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'run only for specific projections [FQCN]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        (new ProjectorHelper($this->projectorResolver))->dropProjection(
            ...$this->projectors(
                $input->getOption('projection')
            )
        );

        $console->success('projection deleted');

        return 0;
    }
}
