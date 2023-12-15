<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class AggregateIdNotSupported extends RuntimeException
{
    /** @param class-string $className */
    public function __construct(string $className, mixed $value)
    {
        parent::__construct(
            sprintf(
                'aggregate id in class "%s" must be instance of "%s", got "%s"',
                $className,
                AggregateRootId::class,
                get_debug_type($value),
            ),
        );
    }
}
