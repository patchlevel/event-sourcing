<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[AsCommand(
    'event-sourcing:database:drop',
    'drop eventstore database',
)]
final class DatabaseDropCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private DoctrineHelper $helper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('if-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database doesn\'t exist')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Set this parameter to execute this action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $databaseName = $this->helper->databaseName($this->connection);
        $tempConnection = $this->helper->copyConnectionWithoutDatabase($this->connection);

        $force = InputHelper::bool($input->getOption('force'));

        if (!$force) {
            $console->caution('This operation should not be executed in a production environment.');
            $console->warning(sprintf('Would drop the database "%s". Please run the operation with --force to execute.', $databaseName));
            $console->caution('All data will be lost!');

            return 2;
        }

        $ifExists = InputHelper::bool($input->getOption('if-exists'));
        $hasDatabase = $this->helper->hasDatabase($tempConnection, $databaseName);

        if ($ifExists && !$hasDatabase) {
            $console->warning(sprintf('Database "%s" doesn\'t exist. Skipped.', $databaseName));

            return 0;
        }

        try {
            $this->helper->dropDatabase($tempConnection, $databaseName);
            $console->success(sprintf('Dropped database "%s"', $databaseName));

            return 0;
        } catch (Throwable $e) {
            $console->error(sprintf('Could not drop database "%s"', $databaseName));
            $console->error($e->getMessage());

            return 3;
        }
    }
}
