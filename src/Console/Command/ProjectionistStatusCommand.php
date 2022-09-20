<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projectionist;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projectionist:status',
    'TODO'
)]
final class ProjectionistStatusCommand extends Command
{
    public function __construct(
        private readonly Projectionist $projectionist
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);
        $states = $this->projectionist->status();

        $io->table(
            [
                'name',
                'version',
                'position',
                'status',
            ],
            array_map(
                fn(ProjectorState $state) => [
                    $state->id()->name(),
                    $state->id()->version(),
                    $state->position(),
                    $state->status()->value,
                ],
                $states
            )
        );

        return 0;
    }
}
