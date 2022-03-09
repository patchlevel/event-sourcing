<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
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
    protected ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        parent::__construct();

        $this->projectionHandler = $projectionHandler;
    }

    /**
     * @return non-empty-array<class-string<Projection>>|null
     */
    protected function normalizeProjectionOption(mixed $value): ?array
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

    /**
     * @param non-empty-array<class-string<Projection>> $onlyProjections
     */
    protected function filterProjectionInProjectionHandler(
        ProjectionHandler $projectionHandler,
        array $onlyProjections
    ): DefaultProjectionHandler {
        if (!$projectionHandler instanceof DefaultProjectionHandler) {
            throw new InvalidArgumentException(
                sprintf(
                    'Filtering projections is only supported with "%s", but "%s" was used.',
                    DefaultProjectionHandler::class,
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

        return new DefaultProjectionHandler(
            $projections,
            $projectionHandler->metadataFactory()
        );
    }
}
