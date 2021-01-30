<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

class DeleteMiddleware implements Middleware
{
    /** @var list<class-string<AggregateChanged>> */
    private array $classes;

    /**
     * @param list<class-string<AggregateChanged>> $classes
     */
    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    /**
     * @return list<AggregateChanged>
     */
    public function __invoke(AggregateChanged $aggregateChanged): array
    {
        foreach ($this->classes as $class) {
            if ($aggregateChanged instanceof $class) {
                return [];
            }
        }

        return [$aggregateChanged];
    }
}
