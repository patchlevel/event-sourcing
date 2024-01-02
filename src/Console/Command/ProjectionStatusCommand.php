<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_map;

#[AsCommand(
    'event-sourcing:projection:status',
    'View the current status of the projections',
)]
final class ProjectionStatusCommand extends ProjectionCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            'id',
            InputArgument::OPTIONAL,
            'The projection to display more information about',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $id = InputHelper::nullableString($input->getArgument('id'));
        $projections = $this->projectionist->projections();

        if ($id === null) {
            $io->table(
                [
                    'id',
                    'name',
                    'version',
                    'position',
                    'status',
                    'error message',
                ],
                array_map(
                    static fn (Projection $projection) => [
                        $projection->id()->toString(),
                        $projection->id()->name(),
                        $projection->id()->version(),
                        $projection->position(),
                        $projection->status()->value,
                        $projection->projectionError()?->errorMessage,
                    ],
                    [...$projections],
                ),
            );

            return 0;
        }

        $projection = $projections->get(ProjectionId::fromString($id));

        $io->horizontalTable(
            [
                'id',
                'name',
                'version',
                'position',
                'status',
                'error message',
            ],
            [
                [
                    $projection->id()->toString(),
                    $projection->id()->name(),
                    $projection->id()->version(),
                    $projection->position(),
                    $projection->status()->value,
                    $projection->projectionError()?->errorMessage,
                ],
            ],
        );

        $errorObject = $projection->projectionError()?->errorObject;

        if ($errorObject instanceof Throwable) {
            $io->throwable($errorObject);
        }

        return 0;
    }
}
