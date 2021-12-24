<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

final class DatabaseCreateCommand extends Command
{
    private Store $store;
    private DoctrineHelper $helper;

    public function __construct(Store $store, DoctrineHelper $helper)
    {
        parent::__construct();

        $this->store = $store;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:database:create')
            ->setDescription('create eventstore database')
            ->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $store = $this->store;

        if (!$store instanceof DoctrineStore) {
            $console->error('Store is not supported!');

            return parent::FAILURE;
        }

        $connection = $store->connection();

        $databaseName = $this->helper->databaseName($connection);
        $tempConnection = $this->helper->copyConnectionWithoutDatabase($connection);

        $ifNotExists = InputHelper::bool($input->getOption('if-not-exists'));
        $hasDatabase = $this->helper->hasDatabase($tempConnection, $databaseName);

        if ($ifNotExists && $hasDatabase) {
            $console->warning(sprintf('Database "%s" already exists. Skipped.', $databaseName));
            $tempConnection->close();

            return parent::SUCCESS;
        }

        try {
            $this->helper->createDatabase($tempConnection, $databaseName);
            $console->success(sprintf('Created database "%s"', $databaseName));
        } catch (Throwable $e) {
            $console->error(sprintf('Could not create database "%s"', $databaseName));
            $console->error($e->getMessage());

            $tempConnection->close();

            return parent::INVALID;
        }

        $tempConnection->close();

        return parent::SUCCESS;
    }
}
