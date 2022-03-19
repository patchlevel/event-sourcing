<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @internal
 */
final class ProjectionMetadata
{
    /** @var array<class-string<AggregateChanged>, ProjectionHandleMetadata> */
    public array $handleMethods = [];

    public ?string $createMethod = null;

    public ?string $dropMethod = null;
}
