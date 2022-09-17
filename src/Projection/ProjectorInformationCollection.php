<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<string, ProjectorInformation>
 */
final class ProjectorInformationCollection implements Countable, IteratorAggregate
{
    /** @var array<string, ProjectorInformation> */
    private readonly array $projectorInformation;

    /**
     * @param list<ProjectorInformation> $projectorInformationList
     */
    public function __construct(array $projectorInformationList = [])
    {
        $result = [];

        foreach ($projectorInformationList as $projectorInformation) {
            $result[$projectorInformation->projectorState->id()->toString()] = $projectorInformation;
        }

        $this->projectorInformation = $result;
    }

    public function get(ProjectorId $projectorId): ProjectorInformation
    {
        if (!$this->has($projectorId)) {
            throw new ProjectorInformationNotFound($projectorId);
        }

        return $this->projectorInformation[$projectorId->toString()];
    }

    public function has(ProjectorId $projectorId): bool
    {
        return array_key_exists($projectorId->toString(), $this->projectorInformation);
    }

    public function add(ProjectorInformation $information): self
    {
        return new self(
            [
                ...array_values($this->projectorInformation),
                $information,
            ]
        );
    }

    public function minProjectorPosition(): int
    {
        $min = 0;

        foreach ($this->projectorInformation as $projectorInformation) {
            if ($projectorInformation->projectorState->position() >= $min) {
                continue;
            }

            $min = $projectorInformation->projectorState->position();
        }

        return $min;
    }

    public function filterByProjectorStatus(ProjectorStatus $status): self
    {
        $projectors = array_filter(
            $this->projectorInformation,
            static fn (ProjectorInformation $information) => $information->projectorState->status() === $status
        );

        return new self(array_values($projectors));
    }

    public function count(): int
    {
        return count($this->projectorInformation);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->projectorInformation);
    }
}
