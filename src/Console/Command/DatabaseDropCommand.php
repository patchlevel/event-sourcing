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
            $console->error('store is not supported');

            return 1;
        }

        $connection = $store->connection();
        $databaseName = $this->databaseName($connection);

        $force = InputHelper::bool($input->getOption('force'));

        if (!$force) {
            $console->writeln('<error>ATTENTION:</error> This operation should not be executed in a production environment.');
            $console->writeln('');
            $console->writeln(sprintf('<info>Would drop the database <comment>%s</comment>.</info>', $databaseName));
            $console->writeln('Please run the operation with --force to execute');
            $console->writeln('<error>All data will be lost!</error>');

            return 1;
        }

        $ifExists = InputHelper::bool($input->getOption('if-exists'));
        $hasDatabase = in_array($databaseName, $connection->createSchemaManager()->listDatabases());

        if ($ifExists && !$hasDatabase) {
            $console->writeln(sprintf('<info>Database <comment>%s</comment> doesn\'t exist. Skipped.</info>', $databaseName));

            return 0;
        }

        try {
            $connection->createSchemaManager()->dropDatabase($databaseName);
            $console->writeln(sprintf('<info>Dropped database <comment>%s</comment></info>', $databaseName));

            return 0;
        } catch (Throwable $e) {
            $console->writeln(sprintf('<error>Could not drop database <comment>%s</comment></error>', $databaseName));
            $console->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return 2;
        }
    }

    private function databaseName(Connection $connection): string
    {
        $params = $connection->getParams();

        if (isset($params['path'])) {
            return $params['path'];
        }

        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        throw new InvalidArgumentException(
            "Connection does not contain a 'path' or 'dbname' parameter and cannot be created."
        );
    }
}
