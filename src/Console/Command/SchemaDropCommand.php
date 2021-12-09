<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Schema\DryRunSchemaManager;
use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SchemaDropCommand extends Command
{
    private Store $store;
    private SchemaManager $schemaManager;

    public function __construct(Store $store, SchemaManager $schemaManager)
    {
        parent::__construct();

        $this->store = $store;
        $this->schemaManager = $schemaManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:schema:drop')
            ->setDescription('drop eventstore schema')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'dump schema drop queries')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'force schema drop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $dryRun = InputHelper::bool($input->getOption('dry-run'));

        if ($dryRun) {
            if (!$this->schemaManager instanceof DryRunSchemaManager) {
                $console->error('SchemaManager dont support dry-run');

                return 1;
            }

            $actions = $this->schemaManager->dryRunDrop($this->store);

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

        $this->schemaManager->drop($this->store);

        $console->success('schema deleted');

        return 0;
    }
}
