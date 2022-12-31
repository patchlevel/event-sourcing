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
    'event-sourcing:schema:drop',
    'drop eventstore schema'
)]
final class SchemaDropCommand extends Command
{
    private SchemaDirector $schemaDirector;

    public function __construct(SchemaDirector $schemaDirector)
    {
        parent::__construct();

        $this->schemaDirector = $schemaDirector;
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'dump schema drop queries')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'force schema drop');
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

            $actions = $this->schemaDirector->dryRunDrop();

            foreach ($actions as $action) {
                $output->writeln($action);
            }

            return 0;
        }

        $force = InputHelper::bool($input->getOption('force'));

        if (!$force) {
            $console->error('Please run the operation with --force to execute. All data will be lost!');

            return 1;
        }

        $this->schemaDirector->drop();

        $console->success('schema deleted');

        return 0;
    }
}
