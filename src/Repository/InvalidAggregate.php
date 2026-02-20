<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function get_debug_type;
use function sprintf;

/** @experimental */
final class InvalidAggregate extends RepositoryException
{
    public function __construct(
        string $autoInitializeMethod,
        string $aggregateRootClass,
        mixed $return,
    ) {
        parent::__construct(sprintf(
            'The method "%s" in "%s" returned "%s". Expected an instance of "%s".',
            $autoInitializeMethod,
            $aggregateRootClass,
            get_debug_type($return),
            $aggregateRootClass,
        ));
    }
}
