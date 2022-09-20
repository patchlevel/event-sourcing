<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Traversable;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<string, ProjectorState>
 */
final class ProjectorStateCollection implements Countable, IteratorAggregate
{
    /** @var array<string, ProjectorState> */
    private readonly array $projectorStates;

    /**
     * @param list<ProjectorState> $projectorStates
     */
    public function __construct(array $projectorStates = [])
    {
        $result = [];

        foreach ($projectorStates as $projectorState) {
            $result[$projectorState->id()->toString()] = $projectorState;
        }

        $this->projectorStates = $result;
    }

    public function get(ProjectorId $projectorId): ProjectorState
    {
        if (!$this->has($projectorId)) {
            throw new ProjectorStateNotFound();
        }

        return $this->projectorStates[$projectorId->toString()];
    }

    public function has(ProjectorId $projectorId): bool
    {
        return array_key_exists($projectorId->toString(), $this->projectorStates);
    }

    public function add(ProjectorState $information): self
    {
        return new self(
            [
                ...array_values($this->projectorStates),
                $information,
            ]
        );
    }

    public function minProjectorPosition(): int
    {
        $min = 0;

        foreach ($this->projectorStates as $projectorState) {
            if ($projectorState->position() >= $min) {
                continue;
            }

            $min = $projectorState->position();
        }

        return $min;
    }

    public function filterByProjectorStatus(ProjectorStatus $status): self
    {
        $projectors = array_filter(
            $this->projectorStates,
            static fn (ProjectorState $projectorState) => $projectorState->status() === $status
        );

        return new self(array_values($projectors));
    }

    public function count(): int
    {
        return count($this->projectorStates);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->projectorStates);
    }
}
