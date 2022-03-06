<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Symfony\Component\Console\Command\Command;

use function is_array;
use function is_string;
use function is_subclass_of;

abstract class ProjectionCommand extends Command
{
    protected ProjectionHandler $projectionRepository;

    public function __construct(ProjectionHandler $projectionRepository)
    {
        parent::__construct();

        $this->projectionRepository = $projectionRepository;
    }

    /**
     * @return non-empty-array<class-string<Projection>>|null
     */
    protected function projections(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentGiven($value, 'class-string<' . Projection::class . '>[]');
        }

        $result = [];

        foreach ($value as $entry) {
            if (!is_string($entry) || !is_subclass_of($entry, Projection::class)) {
                throw new InvalidArgumentGiven($entry, 'class-string<' . Projection::class . '>');
            }

            $result[] = $entry;
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }
}
