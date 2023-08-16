<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projectionist:rebuild',
    'Rebuild projections (remove & boot)',
)]
final class ProjectionistRebuildCommand extends ProjectionistCommand
{
    public function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'throw-by-error',
                null,
                InputOption::VALUE_NONE,
                'throw exception by error',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->projectionCriteria($input);

        if (!$io->confirm('do you want to rebuild all projections?', false)) {
            return 1;
        }

        $throwByError = InputHelper::bool($input->getOption('throw-by-error'));

        $this->projectionist->remove($criteria);
        $this->projectionist->boot($criteria, null, $throwByError);

        return 0;
    }
}
