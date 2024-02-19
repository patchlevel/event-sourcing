<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\Store\ErrorContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function is_array;
use function sprintf;

/** @psalm-import-type Context from ErrorContext */
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
                    'position',
                    'status',
                    'error message',
                ],
                array_map(
                    static fn (Projection $projection) => [
                        $projection->id(),
                        $projection->position(),
                        $projection->status()->value,
                        $projection->projectionError()?->errorMessage,
                    ],
                    $projections,
                ),
            );

            return 0;
        }

        $projection = $this->findProjection($projections, $id);

        $io->horizontalTable(
            [
                'id',
                'position',
                'status',
                'error message',
            ],
            [
                [
                    $projection->id(),
                    $projection->position(),
                    $projection->status()->value,
                    $projection->projectionError()?->errorMessage,
                ],
            ],
        );

        $contexts = $projection->projectionError()?->errorContext;

        if (is_array($contexts)) {
            foreach ($contexts as $context) {
                $this->displayError($io, $context);
            }
        }

        return 0;
    }

    /** @param Context $context */
    private function displayError(OutputStyle $io, array $context): void
    {
        $io->error($context['message']);

        foreach ($context['trace'] as $trace) {
            $io->writeln(sprintf('%s: %s', $trace['file'] ?? '#unknown', $trace['line'] ?? '#unknown'));
        }
    }

    /** @param list<Projection> $projections */
    private function findProjection(array $projections, string $id): Projection
    {
        foreach ($projections as $projection) {
            if ($projection->id() === $id) {
                return $projection;
            }
        }

        throw new ProjectionNotFound($id);
    }
}
