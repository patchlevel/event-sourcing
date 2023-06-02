<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:schema:create',
    'create eventstore schema',
)]
final class SchemaCreateCommand extends Command
{
    public function __construct(private SchemaDirector $schemaDirector)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'dump schema create queries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $dryRun = InputHelper::bool($input->getOption('dry-run'));

        if ($dryRun) {
            if (!$this->schemaDirector instanceof DryRunSchemaDirector) {
                $console->error('SchemaDirector dont support dry-run');

                return 1;
            }

            $actions = $this->schemaDirector->dryRunCreate();

            foreach ($actions as $action) {
                $output->writeln($action);
            }

            return 0;
        }

        $this->schemaDirector->create();

        $console->success('schema created');

        return 0;
    }
}
