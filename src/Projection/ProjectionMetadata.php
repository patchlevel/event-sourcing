<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @internal
 */
class ProjectionMetadata
{
    /** @var array<class-string<AggregateChanged>, string> */
    public array $handleMethods = [];

    public ?string $createMethod = null;

    public ?string $dropMethod = null;
}
