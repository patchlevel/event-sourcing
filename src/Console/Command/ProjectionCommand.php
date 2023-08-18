<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function array_map;
use function array_values;
use function is_array;
use function is_string;

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
            );
    }

    protected function projectionCriteria(InputInterface $input): ProjectionCriteria
    {
        return new ProjectionCriteria(
            $this->projectionIds($input),
        );
    }

    /** @return list<ProjectionId>|null */
    private function projectionIds(InputInterface $input): array|null
    {
        $ids = $input->getOption('id');

        if (!$ids) {
            return null;
        }

        if (!is_array($ids)) {
            throw new InvalidArgumentGiven($ids, 'list<string>');
        }

        return array_values(
            array_map(
                static function (mixed $id) use ($ids): ProjectionId {
                    if (!is_string($id)) {
                        throw new InvalidArgumentGiven($ids, 'list<string>');
                    }

                    return ProjectionId::fromString($id);
                },
                $ids,
            ),
        );
    }
}
