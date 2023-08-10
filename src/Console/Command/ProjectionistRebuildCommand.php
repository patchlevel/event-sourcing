<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Projection\Projectionist\Listener\ThrowErrorListener;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    'event-sourcing:projectionist:rebuild',
    'Rebuild projections (remove & boot)',
)]
final class ProjectionistRebuildCommand extends ProjectionistCommand
{
    public function __construct(
        Projectionist $projectionist,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($projectionist);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $criteria = $this->projectionCriteria($input);

        if (!$io->confirm('do you want to rebuild all projections?', false)) {
            return 1;
        }

        $this->eventDispatcher->addSubscriber(new ThrowErrorListener());

        $this->projectionist->remove($criteria);
        $this->projectionist->boot($criteria);

        return 0;
    }
}
