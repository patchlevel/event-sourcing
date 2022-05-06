<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Symfony\Component\Console\Command\Command;

use function array_filter;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;

abstract class ProjectionCommand extends Command
{
    private ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        parent::__construct();

        $this->projectionHandler = $projectionHandler;
    }

    protected function projectionHandler(mixed $projectionOption): ProjectionHandler
    {
        $normalizedProjectionOption = $this->normalizeProjectionOption($projectionOption);

        if (!$normalizedProjectionOption) {
            return $this->projectionHandler;
        }

        return $this->filterProjectionInProjectionHandler(
            $this->projectionHandler,
            $normalizedProjectionOption
        );
    }

    /**
     * @return non-empty-array<class-string<Projection>>|null
     */
    private function normalizeProjectionOption(mixed $option): ?array
    {
        if (is_string($option)) {
            $option = [$option];
        }

        if (!is_array($option)) {
            throw new InvalidArgumentGiven($option, 'class-string<' . Projection::class . '>[]');
        }

        $result = [];

        foreach ($option as $entry) {
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

    /**
     * @param non-empty-array<class-string<Projection>> $onlyProjections
     */
    private function filterProjectionInProjectionHandler(
        ProjectionHandler $projectionHandler,
        array $onlyProjections
    ): MetadataAwareProjectionHandler {
        if (!$projectionHandler instanceof MetadataAwareProjectionHandler) {
            throw new InvalidArgumentException(
                sprintf(
                    'Filtering projections is only supported with "%s", but "%s" was used.',
                    MetadataAwareProjectionHandler::class,
                    $projectionHandler::class
                )
            );
        }

        $projections = array_values(
            array_filter(
                [...$projectionHandler->projections()],
                static fn (Projection $projection): bool => in_array($projection::class, $onlyProjections)
            )
        );

        return new MetadataAwareProjectionHandler(
            $projections,
            $projectionHandler->metadataFactory()
        );
    }
}
