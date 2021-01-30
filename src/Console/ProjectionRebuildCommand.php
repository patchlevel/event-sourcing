<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StreamableStoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionRepositoryTarget;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectionRebuildCommand extends Command
{
    private Store $store;
    private ProjectionRepository $projectionRepository;

    public function __construct(Store $store, ProjectionRepository $projectionRepository)
    {
        parent::__construct();

        $this->store = $store;
        $this->projectionRepository = $projectionRepository;
    }

    protected function configure(): void
    {
        $this->setName('event-sourcing:projection:rebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        $store = $this->store;

        if (!$store instanceof StreamableStore) {
            $console->error('store is not supported');

            return 1;
        }

        $pipeline = new Pipeline(
            new StreamableStoreSource($store),
            new ProjectionRepositoryTarget($this->projectionRepository)
        );

        $console->progressStart($pipeline->count());

        $pipeline->run(static function () use ($console): void {
            $console->progressAdvance();
        });

        $console->progressFinish();

        return 0;
    }
}
