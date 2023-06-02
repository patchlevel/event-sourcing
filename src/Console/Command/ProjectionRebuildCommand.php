<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectorRepositoryTarget;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function is_string;
use function sprintf;

#[AsCommand(
    'event-sourcing:projection:rebuild',
    'rebuild projection',
)]
final class ProjectionRebuildCommand extends ProjectionCommand
{
    public function __construct(
        private StreamableStore $store,
        ProjectorRepository $projectorRepository,
        private ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
    ) {
        parent::__construct($projectorRepository);
    }

    protected function configure(): void
    {
        $this
            ->addOption('recreate', 'r', InputOption::VALUE_NONE, 'drop and create projections')
            ->addOption(
                'until',
                'u',
                InputOption::VALUE_REQUIRED,
                'create the projection up to a point in time [2017-02-02 12:00]',
            )
            ->addOption(
                'projection',
                'p',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'run only for specific projections [FQCN]',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $projectors = $this->projectors($input->getOption('projection'));

        if (InputHelper::bool($input->getOption('recreate'))) {
            (new ProjectorHelper($this->projectorResolver))->dropProjection(
                ...$projectors,
            );

            $console->success('projection schema deleted');

            (new ProjectorHelper($this->projectorResolver))->createProjection(
                ...$projectors,
            );

            $console->success('projection schema created');
        }

        $until = InputHelper::nullableString($input->getOption('until'));

        $middlewares = [];

        if (is_string($until)) {
            try {
                $date = new DateTimeImmutable($until);
            } catch (Throwable) {
                $console->error(sprintf('date "%s" not supported. the format should be "2017-02-02 12:00"', $until));

                return 1;
            }

            $middlewares[] = new UntilEventMiddleware($date);
        }

        $pipeline = new Pipeline(
            new StoreSource($this->store),
            new ProjectorRepositoryTarget(
                new InMemoryProjectorRepository($projectors),
                $this->projectorResolver,
            ),
            $middlewares,
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
