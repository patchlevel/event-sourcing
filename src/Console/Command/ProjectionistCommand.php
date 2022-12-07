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

abstract class ProjectionistCommand extends Command
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
                InputOption::VALUE_IS_ARRAY,
                'filter by projection id'
            );
    }

    protected function projectorCriteria(InputInterface $input): ProjectionCriteria
    {
        return new ProjectionCriteria(
            $this->projectionIdFilter($input)
        );
    }

    /**
     * @return list<ProjectionId>|null
     */
    private function projectionIdFilter(InputInterface $input): ?array
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
                $ids
            )
        );
    }
}
