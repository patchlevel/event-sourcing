<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use function array_key_exists;
use function array_values;

final class Criteria
{
    /** @var array<class-string, object> */
    private readonly array $criteria;

    public function __construct(object ...$criteria)
    {
        $result = [];

        foreach ($criteria as $criterion) {
            $result[$criterion::class] = $criterion;
        }

        $this->criteria = $result;
    }

    /**
     * @param class-string<T> $criteriaClass
     *
     * @return T
     *
     * @template T of object
     */
    public function get(string $criteriaClass): object
    {
        if (!array_key_exists($criteriaClass, $this->criteria)) {
            throw new CriterionNotFound($criteriaClass);
        }

        return $this->criteria[$criteriaClass];
    }

    public function has(string $criteriaClass): bool
    {
        return array_key_exists($criteriaClass, $this->criteria);
    }

    /** @return list<object> */
    public function all(): array
    {
        return array_values($this->criteria);
    }

    public function add(object $criteria): self
    {
        return new self(
            ...$this->all(),
            ...[$criteria],
        );
    }

    public function remove(string $criteriaClass): self
    {
        $criteria = $this->criteria;
        unset($criteria[$criteriaClass]);

        return new self(...$criteria);
    }
}
