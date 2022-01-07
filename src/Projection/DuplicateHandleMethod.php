<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function sprintf;

final class DuplicateHandleMethod extends ProjectionException
{
    /**
     * @param class-string<Projection>       $projection
     * @param class-string<AggregateChanged> $event
     */
    public function __construct(string $projection, string $event, string $fistMethod, string $secondMethod)
    {
        parent::__construct(
            sprintf(
                'Two methods "%s" and "%s" on the projection "%s" want to handle the same event "%s".',
                $fistMethod,
                $secondMethod,
                $projection,
                $event
            )
        );
    }
}
