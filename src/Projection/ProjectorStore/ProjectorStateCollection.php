<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<int, ProjectorState>
 * @psalm-immutable
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
            if (array_key_exists($projectorState->id()->toString(), $result)) {
                throw new DuplicateProjectorId($projectorState->id());
            }

            $result[$projectorState->id()->toString()] = $projectorState;
        }

        $this->projectorStates = $result;
    }

    public function get(ProjectorId $projectorId): ProjectorState
    {
        if (!$this->has($projectorId)) {
            throw new ProjectorStateNotFound($projectorId);
        }

        return $this->projectorStates[$projectorId->toString()];
    }

    public function has(ProjectorId $projectorId): bool
    {
        return array_key_exists($projectorId->toString(), $this->projectorStates);
    }

    public function add(ProjectorState $state): self
    {
        if ($this->has($state->id())) {
            throw new DuplicateProjectorId($state->id());
        }

        return new self(
            [
                ...array_values($this->projectorStates),
                $state,
            ]
        );
    }

    public function minProjectorPosition(): int
    {
        $min = null;

        foreach ($this->projectorStates as $projectorState) {
            if ($min !== null && $projectorState->position() >= $min) {
                continue;
            }

            $min = $projectorState->position();
        }

        return $min ?: 0;
    }

    /**
     * @param callable(ProjectorState $state):bool $callable
     */
    public function filter(callable $callable): self
    {
        $projectors = array_filter(
            $this->projectorStates,
            $callable
        );

        return new self(array_values($projectors));
    }

    public function filterByProjectorStatus(ProjectorStatus $status): self
    {
        return $this->filter(static fn (ProjectorState $projectorState) => $projectorState->status() === $status);
    }

    public function filterByCriteria(ProjectorCriteria $criteria): self
    {
        return $this->filter(
            static function (ProjectorState $projectorState) use ($criteria): bool {
                if ($criteria->ids !== null) {
                    foreach ($criteria->ids as $id) {
                        if ($projectorState->id()->equals($id)) {
                            return true;
                        }
                    }

                    return false;
                }

                return true;
            }
        );
    }

    public function count(): int
    {
        return count($this->projectorStates);
    }

    /**
     * @return ArrayIterator<int, ProjectorState>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array_values($this->projectorStates));
    }
}
