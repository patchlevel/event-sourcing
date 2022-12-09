<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use ArrayIterator;
use Countable;
use IteratorAggregate;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<int, Projection>
 */
final class ProjectionCollection implements Countable, IteratorAggregate
{
    /** @var array<string, Projection> */
    private readonly array $projections;

    /**
     * @param list<Projection> $projections
     */
    public function __construct(array $projections = [])
    {
        $result = [];

        foreach ($projections as $projection) {
            if (array_key_exists($projection->id()->toString(), $result)) {
                throw new DuplicateProjectionId($projection->id());
            }

            $result[$projection->id()->toString()] = $projection;
        }

        $this->projections = $result;
    }

    public function get(ProjectionId $projectorId): Projection
    {
        if (!$this->has($projectorId)) {
            throw new ProjectionNotFound($projectorId);
        }

        return $this->projections[$projectorId->toString()];
    }

    public function has(ProjectionId $projectorId): bool
    {
        return array_key_exists($projectorId->toString(), $this->projections);
    }

    public function add(Projection $projection): self
    {
        if ($this->has($projection->id())) {
            throw new DuplicateProjectionId($projection->id());
        }

        return new self(
            [
                ...array_values($this->projections),
                $projection,
            ]
        );
    }

    public function getLowestProjectionPosition(): int
    {
        $min = null;

        foreach ($this->projections as $projection) {
            if ($min !== null && $projection->position() >= $min) {
                continue;
            }

            $min = $projection->position();
        }

        return $min ?: 0;
    }

    /**
     * @param callable(Projection $projection):bool $callable
     */
    public function filter(callable $callable): self
    {
        $projections = array_filter(
            $this->projections,
            $callable
        );

        return new self(array_values($projections));
    }

    public function filterByProjectionStatus(ProjectionStatus $status): self
    {
        return $this->filter(static fn (Projection $projection) => $projection->status() === $status);
    }

    public function filterByCriteria(ProjectionCriteria $criteria): self
    {
        return $this->filter(
            static function (Projection $projection) use ($criteria): bool {
                if ($criteria->ids !== null) {
                    foreach ($criteria->ids as $id) {
                        if ($projection->id()->equals($id)) {
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
        return count($this->projections);
    }

    /**
     * @return ArrayIterator<int, Projection>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array_values($this->projections));
    }
}
