<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projectionist;
use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Patchlevel\EventSourcing\Projection\ProjectorId;
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
                'The maximum number of runs this command should execute'
            );
    }

    protected function projectorCriteria(InputInterface $input): ProjectorCriteria
    {
        return new ProjectorCriteria(
            $this->projectorIdFilter($input)
        );
    }

    /**
     * @return list<ProjectorId>|null
     */
    private function projectorIdFilter(InputInterface $input): ?array
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
                static function (mixed $id) use ($ids): ProjectorId {
                    if (!is_string($id)) {
                        throw new InvalidArgumentGiven($ids, 'list<string>');
                    }

                    return new ProjectorId($id);
                },
                $ids
            )
        );
    }
}
