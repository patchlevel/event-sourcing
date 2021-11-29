<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseCreateCommand extends Command
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
            ->setName('event-sourcing:database:create')
            ->setDescription('create eventstore database');
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
        $databaseName = $connection->getParams()['dbname'];

        $connection->createSchemaManager()->createDatabase($databaseName);

        return 0;
    }
}
