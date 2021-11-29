<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function in_array;
use function is_string;
use function sprintf;

class DatabaseDropCommand extends Command
{
    private Store $store;

    public function __construct(Store $store)
    {
        parent::__construct();

        $this->store = $store;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:database:drop')
            ->setDescription('drop eventstore database')
            ->addOption('if-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database doesn\'t exist')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Set this parameter to execute this action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $store = $this->store;

        if (!$store instanceof DoctrineStore) {
            $console->error('Store is not supported!');

            return 1;
        }

        $connection = $store->connection();
        $databaseName = $this->databaseName($connection);

        $force = InputHelper::bool($input->getOption('force'));

        if (!$force) {
            $console->error('ATTENTION: This operation should not be executed in a production environment.');
            $console->newLine();
            $console->info(sprintf('Would drop the database "%s".', $databaseName));
            $console->writeln('Please run the operation with --force to execute');
            $console->error('All data will be lost!');

            return 2;
        }

        $ifExists = InputHelper::bool($input->getOption('if-exists'));
        $hasDatabase = in_array($databaseName, $connection->createSchemaManager()->listDatabases());

        if ($ifExists && !$hasDatabase) {
            $console->info(sprintf('Database "%s" doesn\'t exist. Skipped.', $databaseName));

            return 0;
        }

        try {
            $connection->createSchemaManager()->dropDatabase($databaseName);
            $console->info(sprintf('Dropped database "%s"', $databaseName));

            return 0;
        } catch (Throwable $e) {
            $console->error(sprintf('Could not drop database "%s"', $databaseName));
            $console->error($e->getMessage());

            return 3;
        }
    }

    private function databaseName(Connection $connection): string
    {
        $params = $connection->getParams();

        if (isset($params['path']) && is_string($params['path'])) {
            return $params['path'];
        }

        if (isset($params['dbname']) && is_string($params['dbname'])) {
            return $params['dbname'];
        }

        throw new InvalidArgumentException(
            "Connection does not contain a 'path' or 'dbname' parameter and cannot be created."
        );
    }
}
