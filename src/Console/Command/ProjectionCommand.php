<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Symfony\Component\Console\Command\Command;

use function is_array;
use function is_object;

abstract class ProjectionCommand extends Command
{
    protected ProjectionHandler $projectionRepository;

    public function __construct(ProjectionHandler $projectionRepository)
    {
        parent::__construct();

        $this->projectionRepository = $projectionRepository;
    }

    /**
     * @return non-empty-array<Projection>|null
     */
    protected function projections(mixed $value): ?array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentGiven($value, Projection::class . '[]');
        }

        $result = [];

        foreach ($value as $entry) {
            if (!is_object($entry)) {
                throw new InvalidArgumentGiven($entry, Projection::class);
            }

            if (!$entry instanceof Projection) {
                throw new InvalidArgumentGiven($entry, Projection::class);
            }

            $result[] = $entry;
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }
}
