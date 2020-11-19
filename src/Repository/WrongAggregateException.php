<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use RuntimeException;

final class WrongAggregateException extends RuntimeException
{
    public function __construct(string $aggregateClass, string $expected)
    {
        parent::__construct(sprintf('aggregate "%s::%s" not found', $aggregateClass, $expected));
    }
}
