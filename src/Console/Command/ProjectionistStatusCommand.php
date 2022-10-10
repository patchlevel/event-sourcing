<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;

#[AsCommand(
    'event-sourcing:projectionist:status',
    'View the current status of the projectors'
)]
final class ProjectionistStatusCommand extends ProjectionistCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);
        $states = $this->projectionist->projectorStates();

        $io->table(
            [
                'name',
                'version',
                'position',
                'status',
            ],
            array_map(
                static fn (ProjectorState $state) => [
                    $state->id()->name(),
                    $state->id()->version(),
                    $state->position(),
                    $state->status()->value,
                ],
                [...$states]
            )
        );

        return 0;
    }
}
