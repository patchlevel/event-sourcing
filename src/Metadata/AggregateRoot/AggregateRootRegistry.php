<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;

use function array_flip;
use function array_key_exists;

final class AggregateRootRegistry
{
    /** @var array<string, class-string<AggregateRootInterface>> */
    private array $nameToClassMap;

    /** @var array<class-string<AggregateRootInterface>, string> */
    private array $classToNameMap;

    /**
     * @param array<string, class-string<AggregateRootInterface>> $aggregateNameToClassMap
     */
    public function __construct(array $aggregateNameToClassMap)
    {
        $this->nameToClassMap = $aggregateNameToClassMap;
        $this->classToNameMap = array_flip($aggregateNameToClassMap);
    }

    /**
     * @param class-string<AggregateRootInterface> $aggregateClass
     */
    public function aggregateName(string $aggregateClass): string
    {
        if (!array_key_exists($aggregateClass, $this->classToNameMap)) {
            throw new AggregateRootClassNotRegistered($aggregateClass);
        }

        return $this->classToNameMap[$aggregateClass];
    }

    /**
     * @return class-string<AggregateRootInterface>
     */
    public function aggregateClass(string $aggregateName): string
    {
        if (!array_key_exists($aggregateName, $this->nameToClassMap)) {
            throw new AggregateRootNameNotRegistered($aggregateName);
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

    /**
     * @return array<string, class-string<AggregateRootInterface>>
     */
    public function aggregateClasses(): array
    {
        return $this->nameToClassMap;
    }

    /**
     * @return array<class-string<AggregateRootInterface>, string>
     */
    public function aggregateNames(): array
    {
        return $this->classToNameMap;
    }
}
