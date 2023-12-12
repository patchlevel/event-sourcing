<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[AsCommand(
    'event-sourcing:database:create',
    'create eventstore database',
)]
final class DatabaseCreateCommand extends Command
{
    public function __construct(private Store $store, private DoctrineHelper $helper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);
        $store = $this->store;

        if (!$store instanceof DoctrineStore) {
            $console->error('Store is not supported!');

            return 1;
        }

        $connection = $store->connection();

        $databaseName = $this->helper->databaseName($connection);
        $tempConnection = $this->helper->copyConnectionWithoutDatabase($connection);

        $ifNotExists = InputHelper::bool($input->getOption('if-not-exists'));
        $hasDatabase = $this->helper->hasDatabase($tempConnection, $databaseName);

        if ($ifNotExists && $hasDatabase) {
            $console->warning(sprintf('Database "%s" already exists. Skipped.', $databaseName));
            $tempConnection->close();

            return 0;
        }

        try {
            $this->helper->createDatabase($tempConnection, $databaseName);
            $console->success(sprintf('Created database "%s"', $databaseName));
        } catch (Throwable $e) {
            $console->error(sprintf('Could not create database "%s"', $databaseName));
            $console->error($e->getMessage());

            $tempConnection->close();

            return 2;
        }

        $tempConnection->close();

        return 0;
    }
}
