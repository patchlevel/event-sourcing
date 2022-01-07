<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Handle
{
    /** @var class-string<AggregateChanged> */
    private string $aggregateChangedClass;

    /**
     * @param class-string<AggregateChanged> $aggregateChangedClass
     */
    public function __construct(string $aggregateChangedClass)
    {
        $this->aggregateChangedClass = $aggregateChangedClass;
    }

    /**
     * @return class-string<AggregateChanged>
     */
    public function aggregateChangedClass(): string
    {
        return $this->aggregateChangedClass;
    }
}
