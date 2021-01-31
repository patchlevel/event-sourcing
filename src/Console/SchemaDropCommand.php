<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Schema\SchemaManager;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchemaDropCommand extends Command
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
        $this->setName('event-sourcing:schema:drop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schemaManager->drop($this->store);

        return 0;
    }
}
