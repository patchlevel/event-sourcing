<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionHandlerTarget;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function is_string;
use function sprintf;

final class ProjectionRebuildCommand extends ProjectionCommand
{
    private Store $store;

    public function __construct(Store $store, ProjectionHandler $projectionHandler)
    {
        parent::__construct($projectionHandler);

        $this->store = $store;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:projection:rebuild')
            ->setDescription('rebuild projection')
            ->addOption('recreate', 'r', InputOption::VALUE_NONE, 'drop and create projections')
            ->addOption('until', 'u', InputOption::VALUE_REQUIRED, 'create the projection up to a point in time [2017-02-02 12:00]')
            ->addOption('projection', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'run only for specific projections [FQCN]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $store = $this->store;

        if (!$store instanceof PipelineStore) {
            $console->error('store is not supported');

            return 1;
        }

        $projectionHandler = $this->projectionHandler($input->getOption('projection'));

        if (InputHelper::bool($input->getOption('recreate'))) {
            $projectionHandler->drop();
            $console->success('projection schema deleted');

            $projectionHandler->create();
            $console->success('projection schema created');
        }

        $until = InputHelper::nullableString($input->getOption('until'));

        $middlewares = [];

        if (is_string($until)) {
            try {
                $date = new DateTimeImmutable($until);
            } catch (Throwable $exception) {
                $console->error(sprintf('date "%s" not supported. the format should be "2017-02-02 12:00"', $until));

                return 1;
            }

            $middlewares[] = new UntilEventMiddleware($date);
        }

        $pipeline = new Pipeline(
            new StoreSource($store),
            new ProjectionHandlerTarget($projectionHandler),
            $middlewares
        );

        $console->warning('rebuild projections');
        $console->progressStart($pipeline->count());

        $pipeline->run(static function () use ($console): void {
            $console->progressAdvance();
        });

        $console->progressFinish();
        $console->success('finish');

        return 0;
    }
}
