<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class AggregateRootIdNotSupported extends RuntimeException
{
    /** @param class-string $aggregateRootClass */
    public function __construct(string $aggregateRootClass, mixed $value)
    {
        parent::__construct(
            sprintf(
                'aggregate root id in class "%s" must be instance of "%s", got "%s"',
                $aggregateRootClass,
                AggregateRootId::class,
                get_debug_type($value),
            ),
        );
    }
}
