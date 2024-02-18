<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/** @interal */
abstract class ProjectionCommand extends Command
{
    public function __construct(
        protected readonly Projectionist $projectionist,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'id',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'filter by projection id',
            )
            ->addOption(
                'group',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'filter by projection group',
            );
    }

    protected function projectionCriteria(InputInterface $input): ProjectionistCriteria
    {
        return new ProjectionistCriteria(
            InputHelper::nullableStringList($input->getOption('id')),
            InputHelper::nullableStringList($input->getOption('group')),
        );
    }
}
