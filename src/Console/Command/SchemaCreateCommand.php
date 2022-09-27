<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Schema\DryRunSchemaDirector;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:schema:create',
    'create eventstore schema'
)]
final class SchemaCreateCommand extends Command
{
    private Store $store;
    private SchemaManager|SchemaDirector $schemaDirector;

    public function __construct(Store $store, SchemaManager|SchemaDirector $schemaDirector)
    {
        parent::__construct();

        $this->store = $store;
        $this->schemaDirector = $schemaDirector;
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
            if (!$this->schemaDirector instanceof DryRunSchemaManager && !$this->schemaDirector instanceof DryRunSchemaDirector) {
                $console->error('SchemaDirector dont support dry-run');

                return 1;
            }

            $actions = $this->schemaDirector->dryRunCreate($this->store);

            foreach ($actions as $action) {
                $output->writeln($action);
            }

            return 0;
        }

        $this->schemaDirector->create($this->store);

        $console->success('schema created');

        return 0;
    }
}
