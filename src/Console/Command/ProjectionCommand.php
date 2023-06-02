<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Symfony\Component\Console\Command\Command;

use function array_filter;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;

abstract class ProjectionCommand extends Command
{
    public function __construct(private readonly ProjectorRepository $projectorRepository)
    {
        parent::__construct();
    }

    /** @return list<Projector> */
    protected function projectors(mixed $projectionOption): array
    {
        $normalizedProjectionOption = $this->normalizeProjectionOption($projectionOption);

        if (!$normalizedProjectionOption) {
            return $this->projectorRepository->projectors();
        }

        return array_values(
            array_filter(
                [...$this->projectorRepository->projectors()],
                static fn (Projector $projection): bool => in_array($projection::class, $normalizedProjectionOption)
            ),
        );
    }

    /** @return non-empty-array<class-string<Projector>>|null */
    private function normalizeProjectionOption(mixed $option): array|null
    {
        if (is_string($option)) {
            $option = [$option];
        }

        if (!is_array($option)) {
            throw new InvalidArgumentGiven($option, 'class-string<' . Projector::class . '>[]');
        }

        $result = [];

        foreach ($option as $entry) {
            if (!is_string($entry) || !is_subclass_of($entry, Projector::class)) {
                throw new InvalidArgumentGiven($entry, 'class-string<' . Projector::class . '>');
            }

            $result[] = $entry;
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }
}
