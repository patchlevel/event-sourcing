<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;

#[AsCommand(
    'event-sourcing:projectionist:status',
    'View the current status of the projections',
)]
final class ProjectionistStatusCommand extends ProjectionistCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);
        $projections = $this->projectionist->projections();

        $io->table(
            [
                'name',
                'version',
                'position',
                'status',
                'error message',
            ],
            array_map(
                static fn (Projection $projection) => [
                    $projection->id()->name(),
                    $projection->id()->version(),
                    $projection->position(),
                    $projection->status()->value,
                    $projection->errorMessage(),
                ],
                [...$projections],
            ),
        );

        return 0;
    }
}
