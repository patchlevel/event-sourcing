<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use function array_flip;
use function array_key_exists;

final class ChildAggregateRegistry
{
    /** @var array<string, class-string<ChildAggregate>> */
    private array $nameToClassMap;

    /** @var array<class-string<ChildAggregate>, string> */
    private array $classToNameMap;

    /** @param array<string, class-string<ChildAggregate>> $aggregateNameToClassMap */
    public function __construct(array $aggregateNameToClassMap)
    {
        $this->nameToClassMap = $aggregateNameToClassMap;
        $this->classToNameMap = array_flip($aggregateNameToClassMap);
    }

    /** @param class-string<ChildAggregate> $aggregateClass */
    public function aggregateName(string $aggregateClass): string
    {
        if (!array_key_exists($aggregateClass, $this->classToNameMap)) {
            throw new ChildAggregateClassNotRegistered($aggregateClass);
        }

        return $this->classToNameMap[$aggregateClass];
    }

    /** @return class-string<ChildAggregate> */
    public function aggregateClass(string $aggregateName): string
    {
        if (!array_key_exists($aggregateName, $this->nameToClassMap)) {
            throw new ChildAggregateNameNotRegistered($aggregateName);
        }

        return $this->nameToClassMap[$aggregateName];
    }

    public function hasAggregateClass(string $aggregateClass): bool
    {
        return array_key_exists($aggregateClass, $this->classToNameMap);
    }

    public function hasAggregateName(string $aggregateName): bool
    {
        return array_key_exists($aggregateName, $this->nameToClassMap);
    }

    /** @return array<string, class-string<ChildAggregate>> */
    public function aggregateClasses(): array
    {
        return $this->nameToClassMap;
    }

    /** @return array<class-string<ChildAggregate>, string> */
    public function aggregateNames(): array
    {
        return $this->classToNameMap;
    }
}
